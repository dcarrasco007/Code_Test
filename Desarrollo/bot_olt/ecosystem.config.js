// Configuracion de PM2 para mantener el bot corriendo en el servidor.
// TODO: confirmar con el responsable la ruta real de despliegue (cwd,
// interpreter y rutas de log) antes de usar este archivo en el servidor.
module.exports = {
    apps: [{
        name: "bot_olt",
        cwd: "/var/www/bot_olt",
        script: "bot.py",
        interpreter: "/var/www/bot_olt/env/bin/python3",
        instances: 1,
        autorestart: true,
        max_memory_restart: "500M",
        env: { ENVIRONMENT: "production", PYTHONUNBUFFERED: "1" },
        error_file: "/var/www/bot_olt/log/pm2-err.log",
        out_file: "/var/www/bot_olt/log/pm2-out.log",
        log_date_format: "YYYY-MM-DD HH:mm:ss"
    }]
}
