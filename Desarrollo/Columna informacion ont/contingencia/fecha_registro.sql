-- Fecha de la ultima carga del .zip ONT (la muestra index_subir_archivo.php como "Ultima carga")
-- Se llena sola en cada INSERT de submit_ONT_zip.php; no requiere cambios en los INSERT existentes.
-- Validado: ningun codigo usa SELECT * ni INSERT sin lista de columnas sobre esta tabla.

ALTER TABLE Aden.OLT_INFO_ONT_PRUEBA
    ADD COLUMN `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP;
