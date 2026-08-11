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
from robot.scripts import alarmas, gpon, inventario, trafico
from utils.func import exportar, marker_errors, resumen_texto

# =============================================================
# Autorizacion
# =============================================================


def requiere_autorizacion(func):
    """Deja pasar solo a los chat_id registrados y activos en OLT_BOT_USUARIOS."""

    @functools.wraps(func)
    async def wrapper(update: Update, context: ContextTypes.DEFAULT_TYPE, *args, **kwargs):
        chat_id = update.effective_chat.id
        usuario = await consultas.verificar_usuario(chat_id)

        if not usuario:
            logger.warning(f"Acceso denegado al chat_id {chat_id}")
            await update.effective_message.reply_text(
                "No estas autorizado para usar este bot.\n"
                f"Solicita el acceso indicando tu ID: `{chat_id}`",
                parse_mode=ParseMode.MARKDOWN,
            )
            return

        context.user_data["usuario"] = usuario
        return await func(update, context, *args, **kwargs)

    return wrapper


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


async def pedir_ip_trafico(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """El trafico PON necesita una OLT: se pide la IP y se espera la respuesta."""
    context.user_data["esperando"] = "trafico_ip"
    await update.effective_message.reply_text("Ingresa la IP de la OLT (ej. 10.99.24.68):")


async def _recibir_ip_trafico(update: Update, context: ContextTypes.DEFAULT_TYPE, ip: str):
    await update.effective_chat.send_action("typing")
    titulo, filas = await trafico.obtener(ip)
    await _responder_resultado(update, context, titulo, filas)


OPCIONES: dict = {
    "🗄 Inventario OLT": _consulta_simple(inventario.obtener),
    "🔌 Puertas PON": _consulta_simple(gpon.obtener),
    "🚨 Alarmas criticas": _consulta_simple(alarmas.obtener),
    "📈 Trafico PON": pedir_ip_trafico,
}

# Entradas que esperan un dato del usuario antes de consultar.
ENTRADAS_PENDIENTES: dict = {
    "trafico_ip": _recibir_ip_trafico,
}


def _teclado_menu() -> ReplyKeyboardMarkup:
    """El teclado se dibuja solo a partir de las llaves de OPCIONES."""
    etiquetas = list(OPCIONES)
    filas = [etiquetas[i:i + 2] for i in range(0, len(etiquetas), 2)]
    return ReplyKeyboardMarkup(filas, resize_keyboard=True)


# =============================================================
# Handlers base
# =============================================================


@requiere_autorizacion
async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    nombre = context.user_data["usuario"].get("nombre") or "usuario"
    await update.effective_message.reply_text(
        f"Hola {nombre}. Elige una consulta del menu.",
        reply_markup=_teclado_menu(),
    )


@requiere_autorizacion
async def handle_text(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Routing por diccionario: no se edita al agregar opciones nuevas."""
    texto = (update.effective_message.text or "").strip()

    pendiente = context.user_data.pop("esperando", None)
    if pendiente and pendiente in ENTRADAS_PENDIENTES:
        await ENTRADAS_PENDIENTES[pendiente](update, context, texto)
        return

    handler = OPCIONES.get(texto)
    if not handler:
        await update.effective_message.reply_text(
            "Opcion no reconocida. Elige una del menu.",
            reply_markup=_teclado_menu(),
        )
        return

    try:
        await handler(update, context)
    except Exception as e:
        marker_errors(f"Error al procesar la opcion '{texto}': {e}")
        await update.effective_message.reply_text("Ocurrio un error al procesar la consulta.")


def register_handlers(application: Application) -> None:
    application.add_handler(CommandHandler("start", start))
    application.add_handler(CommandHandler("menu", start))
    application.add_handler(CallbackQueryHandler(descargar, pattern=r"^descargar:"))
    application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_text))
    logger.info(f"Handlers registrados. Opciones del menu: {len(OPCIONES)}")
