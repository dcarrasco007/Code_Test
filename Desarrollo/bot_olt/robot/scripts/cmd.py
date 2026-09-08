"""Handlers de Telegram y armado del menu.

Es la unica capa que conoce comandos, teclados y callbacks. La logica de
negocio vive en los modulos de `robot/scripts/`, y las queries en
`robot/model/consultas.py`.

Para agregar una consulta al menu basta con:
  1. crear el modulo en `robot/scripts/` con una funcion `obtener()` que
     retorne (titulo, filas);
  2. registrar una entrada nueva en el diccionario OPCIONES.
No hay que tocar el routing ni el armado del teclado.
"""

import asyncio
import functools
import os
from typing import Optional

from loguru import logger
from telegram import InlineKeyboardButton, InlineKeyboardMarkup, ReplyKeyboardMarkup, Update
from telegram.constants import ParseMode
from telegram.ext import (
    Application,
    CallbackQueryHandler,
    CommandHandler,
    ContextTypes,
    MessageHandler,
    filters,
)

from robot.model import consultas
from robot.scripts import alarmas, auditoria, gpon, inventario, sesion, trafico, usuarios
from utils import limites, seguridad
from utils.func import (
    exportar,
    marker_errors,
    resumen_texto,
    validar_fecha,
    validar_ip,
    validar_texto,
)

# =============================================================
# Autorizacion
# =============================================================


def _perfiles_admin() -> set:
    """Perfiles habilitados para las opciones privilegiadas, desde el .env.

    Si PERFILES_ADMIN no esta configurado retorna un conjunto vacio: nadie es
    administrador y las opciones privilegiadas no aparecen en el menu. Es el
    default deliberado — una mala configuracion deja el bot mas restrictivo,
    no mas permisivo.
    """
    crudo = os.getenv("PERFILES_ADMIN", "")
    perfiles = set()
    for parte in crudo.split(","):
        parte = parte.strip()
        if parte:
            try:
                perfiles.add(int(parte))
            except ValueError:
                logger.warning(f"PERFILES_ADMIN ignora el valor no numerico '{parte}'")
    return perfiles


def es_admin(usuario: dict) -> bool:
    """True si el perfil del usuario esta en PERFILES_ADMIN."""
    perfiles = _perfiles_admin()
    if not perfiles:
        return False
    try:
        return int((usuario or {}).get("perfil")) in perfiles
    except (TypeError, ValueError):
        return False


async def _autorizar(chat_id: int, refrescar: bool = False) -> Optional[dict]:
    """Verifica al usuario apoyandose en el cache.

    Cachea tambien el resultado negativo: sin eso, quien no esta autorizado
    generaria una query contra produccion por cada mensaje que envie.

    Con `refrescar=True` ignora el cache y va a la base. Se usa antes de
    ejecutar operaciones privilegiadas, para no depender de un permiso que
    pudo revocarse dentro de la ventana del TTL.
    """
    if not refrescar:
        hay_dato, usuario = limites.auth_en_cache(chat_id)
        if hay_dato:
            return usuario

    usuario = await consultas.verificar_usuario(chat_id)
    limites.guardar_auth(chat_id, usuario)
    return usuario


def requiere_autorizacion(func):
    """Filtra cada mensaje antes de que llegue a la base.

    El orden importa: primero lo que no cuesta nada (silenciados), luego el
    limite de peticiones, y solo al final la verificacion —que es la que
    puede terminar en una consulta a produccion.
    """

    @functools.wraps(func)
    async def wrapper(update: Update, context: ContextTypes.DEFAULT_TYPE, *args, **kwargs):
        chat_id = update.effective_chat.id

        # 1. A quien ya insistio de mas no se le responde ni se le consulta.
        if limites.esta_silenciado(chat_id):
            return

        # 2. Limite de peticiones, antes de tocar la base.
        permitido, avisar = limites.permitir(chat_id)
        if not permitido:
            if avisar:
                espera = limites.segundos_para_reintentar(chat_id)
                logger.warning(f"Rate limit alcanzado por chat_id {chat_id}")
                await update.effective_message.reply_text(
                    f"Demasiadas consultas seguidas. Reintenta en {espera} segundos."
                )
            return

        # 3. Autorizacion. Durante un flujo de clave se consulta sin cache: los
        # intentos fallidos y la vigencia de la sesion cambian en cada paso.
        en_flujo = bool(context.user_data.get("flujo_sesion"))
        usuario = await _autorizar(chat_id, refrescar=en_flujo)

        if not usuario:
            responder = limites.registrar_no_autorizado(chat_id)
            logger.warning(f"Acceso denegado al chat_id {chat_id}")
            # Se audita solo mientras no este silenciado: el silenciado acota
            # cuantas filas puede generar alguien insistiendo.
            await auditoria.registrar(
                chat_id, None, auditoria.ACCESO_DENEGADO,
                detalle=(update.effective_message.text or "")[:150],
            )
            if responder:
                await update.effective_message.reply_text(
                    "No estas autorizado para usar este bot.\n"
                    f"Solicita el acceso indicando tu ID: `{chat_id}`",
                    parse_mode=ParseMode.MARKDOWN,
                )
            return

        limites.limpiar_intentos(chat_id)
        context.user_data["usuario"] = usuario

        # 4. Sesion: capa extra de login con la clave propia del bot.
        if not await _gestionar_sesion(update, context, usuario):
            return

        return await func(update, context, *args, **kwargs)

    return wrapper


# =============================================================
# Sesion: login con la clave propia del bot
# =============================================================
#
# La clave del bot es independiente de la del portal. El login pide solo la
# clave: el chat_id ya identifica a la persona.
#
# Los mensajes que contienen una clave se borran del chat apenas se procesan.

FLUJO_LOGIN = "login"
FLUJO_CAMBIO_ACTUAL = "cambio_actual"
FLUJO_CAMBIO_NUEVA = "cambio_nueva"
FLUJO_CAMBIO_REPETIR = "cambio_repetir"

_TEXTO_PEDIR_CLAVE = "Ingresa tu *clave del bot* para continuar:"
_TEXTO_PEDIR_NUEVA = (
    "Define tu nueva clave del bot (minimo %d caracteres, sin espacios):"
    % seguridad.LARGO_MINIMO
)


async def _borrar_mensaje(update: Update) -> None:
    """Borra del chat el mensaje que contenia una clave."""
    try:
        await update.effective_message.delete()
    except Exception as e:  # el bot puede no tener permiso o ser muy antiguo
        logger.warning(f"No se pudo borrar el mensaje con la clave: {e}")


def _limpiar_flujo(context: ContextTypes.DEFAULT_TYPE) -> None:
    context.user_data.pop("flujo_sesion", None)
    context.user_data.pop("clave_nueva", None)


async def _gestionar_sesion(update: Update, context: ContextTypes.DEFAULT_TYPE,
                            usuario: dict) -> bool:
    """Decide si el usuario puede operar. True = puede seguir."""
    chat_id = usuario["chat_id"]
    flujo = context.user_data.get("flujo_sesion")

    # Un mensaje de texto durante un flujo de clave es la respuesta a lo pedido.
    if flujo and update.callback_query is None and update.effective_message:
        texto = update.effective_message.text or ""
        await _borrar_mensaje(update)
        await _paso_flujo(update, context, usuario, flujo, texto.strip())
        return False

    estado = sesion.estado(usuario)

    if estado == sesion.ACTIVO:
        return True

    # Desde un boton no se inicia un flujo de clave: se pide volver al chat.
    if update.callback_query is not None:
        await update.callback_query.answer()
        await update.effective_message.reply_text(
            "Tu sesion expiro. Escribe cualquier mensaje para volver a ingresar."
        )
        return False

    if estado == sesion.BLOQUEADO:
        await update.effective_message.reply_text(
            "Tu cuenta esta bloqueada por intentos fallidos.\n"
            "Reintenta en %d minutos o pide a un administrador que te reasigne la clave."
            % sesion.minutos_restantes_bloqueo(usuario)
        )
        return False

    if estado == sesion.SIN_CLAVE:
        await update.effective_message.reply_text(
            "Aun no tienes clave del bot asignada.\n"
            "Solicitala a un administrador."
        )
        return False

    if estado == sesion.REQUIERE_CAMBIO:
        context.user_data["flujo_sesion"] = FLUJO_CAMBIO_NUEVA
        await update.effective_message.reply_text(
            "Estas usando una clave temporal. Debes cambiarla antes de continuar.\n\n"
            + _TEXTO_PEDIR_NUEVA,
            parse_mode=ParseMode.MARKDOWN,
        )
        return False

    context.user_data["flujo_sesion"] = FLUJO_LOGIN
    await update.effective_message.reply_text(_TEXTO_PEDIR_CLAVE,
                                              parse_mode=ParseMode.MARKDOWN)
    return False


async def _paso_flujo(update: Update, context: ContextTypes.DEFAULT_TYPE,
                      usuario: dict, flujo: str, texto: str) -> None:
    """Procesa un paso de los flujos de login y de cambio de clave."""
    chat_id = usuario["chat_id"]
    mensaje = update.effective_message

    # ---------------------------------------------------- login
    if flujo == FLUJO_LOGIN:
        resultado, dato = await sesion.intentar_login(usuario, texto)
        limites.invalidar_auth(chat_id)

        if resultado == "bloqueado":
            _limpiar_flujo(context)
            await auditoria.registrar(chat_id, usuario, auditoria.CUENTA_BLOQUEADA)
            await mensaje.reply_text(
                "Demasiados intentos fallidos. Tu cuenta quedo bloqueada por %d minutos."
                % dato
            )
            return

        if resultado == "incorrecta":
            await auditoria.registrar(chat_id, usuario, auditoria.LOGIN_FALLIDO,
                                      detalle="intentos restantes: %d" % dato)
            await mensaje.reply_text(
                "Clave incorrecta. Te quedan %d intentos.\n\n%s" % (dato, _TEXTO_PEDIR_CLAVE),
                parse_mode=ParseMode.MARKDOWN,
            )
            return

        await auditoria.registrar(chat_id, usuario, auditoria.LOGIN_OK)

        if resultado == "temporal":
            context.user_data["flujo_sesion"] = FLUJO_CAMBIO_NUEVA
            await mensaje.reply_text(
                "Ingreso correcto, pero tu clave es temporal y debes cambiarla.\n\n"
                + _TEXTO_PEDIR_NUEVA,
                parse_mode=ParseMode.MARKDOWN,
            )
            return

        _limpiar_flujo(context)
        await mensaje.reply_text(
            "Sesion iniciada por %d horas." % sesion.horas_sesion(),
            reply_markup=_teclado_menu(usuario),
        )
        return

    # ------------------------------- cambio: clave actual
    if flujo == FLUJO_CAMBIO_ACTUAL:
        valida = await asyncio.to_thread(seguridad.verificar, texto, usuario.get("pass_bot"))
        if not valida:
            await mensaje.reply_text("Clave actual incorrecta. Ingresala de nuevo:")
            return
        context.user_data["flujo_sesion"] = FLUJO_CAMBIO_NUEVA
        await mensaje.reply_text(_TEXTO_PEDIR_NUEVA, parse_mode=ParseMode.MARKDOWN)
        return

    # ------------------------------- cambio: clave nueva
    if flujo == FLUJO_CAMBIO_NUEVA:
        error = seguridad.validar_clave_nueva(texto, sesion.CLAVE_INICIAL)
        if error:
            await mensaje.reply_text("%s\n\n%s" % (error, _TEXTO_PEDIR_NUEVA),
                                     parse_mode=ParseMode.MARKDOWN)
            return
        context.user_data["clave_nueva"] = texto
        context.user_data["flujo_sesion"] = FLUJO_CAMBIO_REPETIR
        await mensaje.reply_text("Repite la clave nueva para confirmar:")
        return

    # ------------------------------- cambio: repetir
    if flujo == FLUJO_CAMBIO_REPETIR:
        nueva = context.user_data.get("clave_nueva")
        if texto != nueva:
            context.user_data.pop("clave_nueva", None)
            context.user_data["flujo_sesion"] = FLUJO_CAMBIO_NUEVA
            await mensaje.reply_text("Las claves no coinciden.\n\n" + _TEXTO_PEDIR_NUEVA,
                                     parse_mode=ParseMode.MARKDOWN)
            return

        ok = await sesion.cambiar_clave(chat_id, nueva)
        _limpiar_flujo(context)  # descarta la clave en claro de la memoria
        limites.invalidar_auth(chat_id)

        if not ok:
            await mensaje.reply_text("No se pudo guardar la clave. Revisa el log.")
            return

        await auditoria.registrar(chat_id, usuario, auditoria.CAMBIO_CLAVE_BOT)
        await mensaje.reply_text("Clave actualizada.",
                                 reply_markup=_teclado_menu(usuario))
        return

    _limpiar_flujo(context)


# =============================================================
# Envio de resultados
# =============================================================

FORMATOS: dict = {
    "excel": "📊 Excel",
    "csv": "📄 CSV",
    "txt": "📝 TXT",
}


def _teclado_formatos() -> InlineKeyboardMarkup:
    """Botones de descarga, dibujados a partir del diccionario FORMATOS."""
    botones = [
        InlineKeyboardButton(etiqueta, callback_data=f"descargar:{formato}")
        for formato, etiqueta in FORMATOS.items()
    ]
    return InlineKeyboardMarkup([botones])


async def _responder_resultado(update: Update, context: ContextTypes.DEFAULT_TYPE, titulo: str, filas: list):
    """Muestra la vista previa y ofrece la descarga en los formatos disponibles."""
    mensaje = update.effective_message

    if not filas:
        await mensaje.reply_text(f"*{titulo}*\n\nLa consulta no devolvio resultados.",
                                 parse_mode=ParseMode.MARKDOWN)
        return

    context.user_data["ultimo_resultado"] = (titulo, filas)
    await mensaje.reply_text(
        resumen_texto(filas, titulo),
        parse_mode=ParseMode.MARKDOWN,
        reply_markup=_teclado_formatos(),
    )


@requiere_autorizacion
async def descargar(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Genera y envia el archivo del formato elegido con el ultimo resultado."""
    query = update.callback_query
    await query.answer()

    resultado = context.user_data.get("ultimo_resultado")
    if not resultado:
        await query.message.reply_text("No hay una consulta reciente. Vuelve a ejecutarla.")
        return

    titulo, filas = resultado
    formato = query.data.split(":", 1)[1]

    await auditoria.registrar(
        update.effective_chat.id, context.user_data.get("usuario"),
        auditoria.DESCARGA, detalle=titulo, parametro=formato,
    )

    try:
        # pandas y openpyxl son sincronicos y pesados: van en un thread aparte.
        contenido, nombre = await asyncio.to_thread(exportar, filas, formato, titulo)
        await query.message.reply_document(document=contenido, filename=nombre, caption=titulo)
    except Exception as e:
        marker_errors(f"Error al exportar '{titulo}' a {formato}: {e}")
        await query.message.reply_text("No se pudo generar el archivo. Revisa el log.")


# =============================================================
# Opciones del menu
# =============================================================


def _consulta_simple(obtener):
    """Envuelve un `obtener()` sin parametros en un handler del menu."""

    async def handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
        await update.effective_chat.send_action("typing")
        titulo, filas = await obtener()
        await _responder_resultado(update, context, titulo, filas)

    return handler


def _consulta_con_dato(clave: str, pregunta: str, validador, obtener, error: str):
    """Arma el par (preguntar, recibir) para una consulta que necesita un dato.

    Generico a proposito: recibe el validador y la funcion de negocio, asi que
    sirve para cualquier menu futuro que pida una fecha, una IP, un nombre o
    lo que sea. El dato invalido nunca llega a la base — se vuelve a pedir.

    Devuelve dos funciones que se registran en OPCIONES y ENTRADAS_PENDIENTES.
    """

    async def preguntar(update: Update, context: ContextTypes.DEFAULT_TYPE):
        context.user_data["esperando"] = clave
        await update.effective_message.reply_text(pregunta, parse_mode=ParseMode.MARKDOWN)

    async def recibir(update: Update, context: ContextTypes.DEFAULT_TYPE, texto: str):
        valor = validador(texto)
        if valor is None:
            context.user_data["esperando"] = clave  # se vuelve a preguntar
            await update.effective_message.reply_text(
                f"{error}\n\n{pregunta}", parse_mode=ParseMode.MARKDOWN
            )
            return

        await update.effective_chat.send_action("typing")
        titulo, filas = await obtener(valor)
        await _responder_resultado(update, context, titulo, filas)

    return preguntar, recibir


_TEXTO_FECHA = (
    "Ingresa la fecha a consultar en formato *AAAA-MM-DD* (ej. 2026-08-10).\n"
    "Tambien puedes escribir _hoy_ o _ayer_."
)

_TEXTO_IP = "Ingresa la *IP* de la OLT (ej. 10.99.24.68):"

# OLT_TRAFICOGPON es una tabla grande: la consulta se acota siempre por fecha.
pedir_fecha_puertas, _recibir_fecha_puertas = _consulta_con_dato(
    "puertas_fecha", _TEXTO_FECHA, validar_fecha, gpon.obtener, "No entendi la fecha."
)

pedir_ip_trafico, _recibir_ip_trafico = _consulta_con_dato(
    "trafico_ip", _TEXTO_IP, validar_ip, trafico.obtener, "Esa no es una IP valida."
)


# =============================================================
# Reseteo de contrasena (opcion privilegiada, escribe en la base)
# =============================================================


async def pedir_usuario_reset(update: Update, context: ContextTypes.DEFAULT_TYPE):
    context.user_data["esperando"] = "reset_usuario"
    await update.effective_message.reply_text(
        "Ingresa el *nombre de usuario* del portal a resetear.\n"
        "Debe escribirse completo y exacto.",
        parse_mode=ParseMode.MARKDOWN,
    )


async def _recibir_usuario_reset(update: Update, context: ContextTypes.DEFAULT_TYPE, texto: str):
    """Busca el usuario y pide confirmacion antes de tocar nada."""
    mensaje = update.effective_message

    nombre = validar_texto(texto, largo_maximo=60)
    if not nombre:
        context.user_data["esperando"] = "reset_usuario"
        await mensaje.reply_text(
            "Nombre de usuario invalido (vacio o demasiado largo). Intenta de nuevo."
        )
        return

    await update.effective_chat.send_action("typing")
    resultado, fila = await usuarios.buscar(nombre)

    if resultado == usuarios.NO_EXISTE:
        await mensaje.reply_text(f"El usuario '{texto}' no existe. Solicitud cancelada.")
        return

    if resultado == usuarios.DUPLICADO:
        await mensaje.reply_text(
            f"'{texto}' devolvio mas de una coincidencia. "
            "Solicitud cancelada por seguridad: revisalo en el portal."
        )
        return

    # Una sola coincidencia: se guarda el candidato y se pide confirmacion.
    context.user_data["reset_candidato"] = fila["usuario"]

    botones = InlineKeyboardMarkup([[
        InlineKeyboardButton("✅ Confirmar", callback_data="reset:confirmar"),
        InlineKeyboardButton("❌ Cancelar", callback_data="reset:cancelar"),
    ]])

    await mensaje.reply_text(
        f"*{usuarios.TITULO}*\n\n"
        f"Usuario: `{fila['usuario']}`\n"
        f"Estado actual: {usuarios.describir_estado(fila.get('estado'))}\n"
        f"Ultima conexion: {fila.get('last_connection') or 'sin registro'}\n\n"
        f"Se dejara la clave en `{usuarios.PASSWORD_POR_DEFECTO}` y el usuario quedara *activo*.\n"
        "¿Confirmas?",
        parse_mode=ParseMode.MARKDOWN,
        reply_markup=botones,
    )


@requiere_autorizacion
async def confirmar_reset(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Ejecuta o cancela el reseteo segun el boton que se toco."""
    query = update.callback_query
    await query.answer()

    candidato = context.user_data.pop("reset_candidato", None)
    accion = query.data.split(":", 1)[1]

    if accion == "cancelar":
        await query.edit_message_text("Solicitud cancelada. No se modifico nada.")
        return

    if not candidato:
        await query.edit_message_text(
            "La solicitud expiro o ya fue procesada. Vuelve a iniciarla desde el menu."
        )
        return

    # Se revalida contra la base ignorando el cache: es una escritura
    # privilegiada, y no debe apoyarse en un permiso que pudo revocarse
    # dentro de la ventana del TTL.
    solicitante = await _autorizar(update.effective_chat.id, refrescar=True) or {}

    if not es_admin(solicitante):
        logger.warning(
            f"Intento de reseteo sin permisos: chat_id {update.effective_chat.id} sobre '{candidato}'"
        )
        await query.edit_message_text("No tienes permisos para ejecutar esta operacion.")
        return

    ok = await usuarios.resetear(candidato, solicitante)

    await auditoria.registrar(
        update.effective_chat.id, solicitante, auditoria.RESETEO_PASSWORD,
        detalle="OK" if ok else "SIN EFECTO", parametro=candidato,
    )

    if ok:
        await query.edit_message_text(
            f"Contrasena de `{candidato}` cambiada a `{usuarios.PASSWORD_POR_DEFECTO}`.\n"
            "El usuario quedo activo y debera cambiarla al ingresar.",
            parse_mode=ParseMode.MARKDOWN,
        )
    else:
        await query.edit_message_text(
            f"No se pudo resetear a '{candidato}': la base no reporto cambios. Revisa el log."
        )


# =============================================================
# Menus de sesion y clave
# =============================================================


async def pedir_cambio_clave(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Cambio voluntario: se pide la clave actual antes de la nueva.

    Aunque la sesion este vigente, exigir la clave actual evita que alguien
    que tome el telefono desbloqueado se apropie de la cuenta cambiandola.
    """
    context.user_data["flujo_sesion"] = FLUJO_CAMBIO_ACTUAL
    await update.effective_message.reply_text("Ingresa tu clave actual del bot:")


async def cerrar_sesion(update: Update, context: ContextTypes.DEFAULT_TYPE):
    chat_id = update.effective_chat.id
    usuario = context.user_data.get("usuario")

    await sesion.cerrar(chat_id)
    limites.invalidar_auth(chat_id)
    _limpiar_flujo(context)

    await auditoria.registrar(chat_id, usuario, auditoria.LOGOUT)
    await update.effective_message.reply_text(
        "Sesion cerrada. Escribe cualquier mensaje para volver a ingresar."
    )


async def pedir_usuario_clave_bot(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Menu de administrador: asignar la clave inicial del bot a alguien."""
    context.user_data["esperando"] = "clave_bot_usuario"
    await update.effective_message.reply_text(
        "Ingresa el *usuario* al que le asignaras la clave del bot.\n"
        "Debe estar dado de alta previamente en el bot.",
        parse_mode=ParseMode.MARKDOWN,
    )


async def _recibir_usuario_clave_bot(update: Update, context: ContextTypes.DEFAULT_TYPE,
                                     texto: str):
    mensaje = update.effective_message

    nombre = validar_texto(texto, largo_maximo=60)
    if not nombre:
        context.user_data["esperando"] = "clave_bot_usuario"
        await mensaje.reply_text("Usuario invalido. Intenta de nuevo.")
        return

    await update.effective_chat.send_action("typing")
    fila = await sesion.buscar_para_asignar(nombre)

    if not fila:
        await mensaje.reply_text(
            "El usuario '%s' no esta dado de alta en el bot.\n"
            "Primero hay que registrarlo en OLT_BOT_USUARIOS." % nombre
        )
        return

    context.user_data["clave_bot_candidato"] = fila["usuario"]

    estado_clave = "sin clave"
    if int(fila.get("tiene_clave") or 0):
        estado_clave = "temporal" if int(fila.get("pass_temporal") or 0) else "definitiva"

    botones = InlineKeyboardMarkup([[
        InlineKeyboardButton("Confirmar", callback_data="clavebot:confirmar"),
        InlineKeyboardButton("Cancelar", callback_data="clavebot:cancelar"),
    ]])

    await mensaje.reply_text(
        "*Asignar clave del bot*\n\n"
        "Usuario: `%s`\n"
        "Nombre: %s\n"
        "Perfil (portal): %s\n"
        "Clave actual: %s\n\n"
        "Se le asignara la clave inicial `%s` y debera cambiarla al ingresar.\n"
        "Si tenia una sesion abierta, se cerrara.\n"
        "¿Confirmas?"
        % (fila["usuario"], fila.get("nombre") or "sin registro",
           fila.get("perfil") if fila.get("perfil") is not None else "sin perfil",
           estado_clave, sesion.CLAVE_INICIAL),
        parse_mode=ParseMode.MARKDOWN,
        reply_markup=botones,
    )


@requiere_autorizacion
async def confirmar_clave_bot(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer()

    candidato = context.user_data.pop("clave_bot_candidato", None)
    accion = query.data.split(":", 1)[1]

    if accion == "cancelar":
        await query.edit_message_text("Solicitud cancelada. No se modifico nada.")
        return

    if not candidato:
        await query.edit_message_text("La solicitud expiro. Vuelve a iniciarla.")
        return

    admin = await _autorizar(update.effective_chat.id, refrescar=True) or {}
    if not es_admin(admin):
        logger.warning(
            "Intento de asignar clave sin permisos: chat_id %s sobre '%s'"
            % (update.effective_chat.id, candidato)
        )
        await query.edit_message_text("No tienes permisos para esta operacion.")
        return

    ok = await sesion.asignar_clave_inicial(candidato, admin)

    await auditoria.registrar(
        update.effective_chat.id, admin, auditoria.ASIGNACION_CLAVE_BOT,
        detalle="OK" if ok else "SIN EFECTO", parametro=candidato,
    )

    if ok:
        await query.edit_message_text(
            "Clave del bot asignada a `%s`.\n"
            "Debe ingresar con `%s` y cambiarla de inmediato."
            % (candidato, sesion.CLAVE_INICIAL),
            parse_mode=ParseMode.MARKDOWN,
        )
    else:
        await query.edit_message_text(
            "No se pudo asignar la clave a '%s'. Revisa el log." % candidato
        )


# =============================================================
# Opciones del menu
# =============================================================

OPCIONES: dict = {
    "🗄 Inventario OLT": _consulta_simple(inventario.obtener),
    "🔌 Puertas PON": pedir_fecha_puertas,
    "🚨 Alarmas criticas": _consulta_simple(alarmas.obtener),
    "📈 Trafico PON": pedir_ip_trafico,
    "🔒 Cambiar mi clave": pedir_cambio_clave,
    "🚪 Cerrar sesion": cerrar_sesion,
}

# Opciones privilegiadas: solo se muestran y se aceptan a los perfiles
# listados en PERFILES_ADMIN del .env.
OPCIONES_ADMIN: dict = {
    "🔑 Resetear contrasena": pedir_usuario_reset,
    "🆕 Asignar clave del bot": pedir_usuario_clave_bot,
}

# Entradas que esperan un dato del usuario antes de consultar.
ENTRADAS_PENDIENTES: dict = {
    "puertas_fecha": _recibir_fecha_puertas,
    "trafico_ip": _recibir_ip_trafico,
    "reset_usuario": _recibir_usuario_reset,
    "clave_bot_usuario": _recibir_usuario_clave_bot,
}

# Entradas pendientes que solo pueden continuar los perfiles admin.
ENTRADAS_ADMIN: set = {"reset_usuario", "clave_bot_usuario"}


def _opciones_para(usuario: dict) -> dict:
    """Opciones visibles para este usuario, segun su perfil."""
    if es_admin(usuario):
        return {**OPCIONES, **OPCIONES_ADMIN}
    return OPCIONES


def _teclado_menu(usuario: Optional[dict] = None) -> ReplyKeyboardMarkup:
    """El teclado se dibuja solo a partir de las opciones del usuario."""
    etiquetas = list(_opciones_para(usuario or {}))
    filas = [etiquetas[i:i + 2] for i in range(0, len(etiquetas), 2)]
    return ReplyKeyboardMarkup(filas, resize_keyboard=True)


# =============================================================
# Handlers base
# =============================================================


@requiere_autorizacion
async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    usuario = context.user_data["usuario"]
    nombre = usuario.get("nombre") or "usuario"

    await auditoria.registrar(
        update.effective_chat.id, usuario, auditoria.INICIO_SESION,
        detalle=(update.effective_message.text or "/start"),
    )

    await update.effective_message.reply_text(
        f"Hola {nombre}. Elige una consulta del menu.",
        reply_markup=_teclado_menu(usuario),
    )


@requiere_autorizacion
async def handle_text(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Routing por diccionario: no se edita al agregar opciones nuevas."""
    texto = (update.effective_message.text or "").strip()
    usuario = context.user_data["usuario"]

    # El menu tiene prioridad sobre un dato pendiente: si el usuario toca otra
    # opcion mientras se le pedia una fecha o una IP, cambia de consulta en vez
    # de quedar atrapado en la pregunta anterior.
    handler = _opciones_para(usuario).get(texto)
    if handler:
        context.user_data.pop("esperando", None)
        # Punto unico de auditoria del menu: cualquier opcion que se agregue
        # a OPCIONES en el futuro queda registrada sin tocar nada mas.
        await auditoria.registrar(
            update.effective_chat.id, usuario, auditoria.CONSULTA, detalle=texto
        )
        try:
            await handler(update, context)
        except Exception as e:
            marker_errors(f"Error al procesar la opcion '{texto}': {e}")
            await update.effective_message.reply_text("Ocurrio un error al procesar la consulta.")
        return

    pendiente = context.user_data.pop("esperando", None)
    if pendiente in ENTRADAS_PENDIENTES:
        # Revalidacion: una entrada privilegiada no continua si el perfil ya
        # no corresponde, aunque la pregunta se haya iniciado antes.
        if pendiente in ENTRADAS_ADMIN and not es_admin(usuario):
            logger.warning(f"Entrada '{pendiente}' bloqueada para chat_id {update.effective_chat.id}")
            await update.effective_message.reply_text("No tienes permisos para esta operacion.")
            return

        # Segundo punto unico: aqui se registra el dato que acompana a la
        # consulta (la fecha, la IP, el usuario a resetear).
        await auditoria.registrar(
            update.effective_chat.id, usuario, auditoria.CONSULTA,
            detalle=pendiente, parametro=texto,
        )
        try:
            await ENTRADAS_PENDIENTES[pendiente](update, context, texto)
        except Exception as e:
            marker_errors(f"Error al procesar la entrada '{pendiente}': {e}")
            await update.effective_message.reply_text("Ocurrio un error al procesar la consulta.")
        return

    await update.effective_message.reply_text(
        "Opcion no reconocida. Elige una del menu.",
        reply_markup=_teclado_menu(usuario),
    )


def register_handlers(application: Application) -> None:
    application.add_handler(CommandHandler("start", start))
    application.add_handler(CommandHandler("menu", start))
    application.add_handler(CallbackQueryHandler(descargar, pattern=r"^descargar:"))
    application.add_handler(CallbackQueryHandler(confirmar_reset, pattern=r"^reset:"))
    application.add_handler(CallbackQueryHandler(confirmar_clave_bot, pattern=r"^clavebot:"))
    application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_text))

    perfiles = _perfiles_admin()
    logger.info(
        f"Handlers registrados. Opciones: {len(OPCIONES)} generales, "
        f"{len(OPCIONES_ADMIN)} privilegiadas. PERFILES_ADMIN={sorted(perfiles) or 'sin configurar'}"
    )
    if not perfiles:
        logger.warning(
            "PERFILES_ADMIN no esta configurado en el .env: "
            "las opciones privilegiadas no estaran disponibles para nadie."
        )
