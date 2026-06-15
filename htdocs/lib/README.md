# Demo Passkey / Huella con Bootstrap y PHP

Este proyecto muestra el flujo de registro y acceso biométrico usando WebAuthn/passkeys desde el navegador.

## Qué hace
- Registra una credencial desde el celular o navegador compatible
- Dispara el lector de huella / Face ID del dispositivo
- Guarda el identificador de la credencial en `storage/passkeys.json`
- Permite un login de prueba con la credencial registrada

## Importante
La huella del usuario no se puede leer ni extraer desde una web. WebAuthn crea una credencial de clave pública asociada al dispositivo y al origen del sitio, con consentimiento del usuario. El servidor guarda la credencial pública o su identificador, no la huella. 

## Uso
1. Coloca este proyecto en tu servidor PHP.
2. Ábrelo en un contexto seguro.
3. Registra una credencial.
4. Luego prueba el botón de ingreso con huella.

## Para producción
Este demo es visual y funcional para probar el flujo.  
Para validar criptográficamente la respuesta en producción, conecta tu app con `web-auth/webauthn-lib` mediante Composer y usa un repositorio de credenciales.
