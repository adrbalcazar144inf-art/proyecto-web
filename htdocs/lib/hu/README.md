# Demo Huella Bootstrap

Esta carpeta contiene una demo visual en Bootstrap 5 para probar el flujo biométrico WebAuthn/passkey desde el navegador.

## Qué hace
- Muestra una pantalla de inicio limpia en Bootstrap.
- Permite **registrar** una passkey/huella desde el navegador.
- Permite **entrar** con esa passkey/huella.
- Guarda el estado en sesión de PHP para ver el flujo.

## Importante
- Debes abrirlo en **HTTPS** o en un entorno local compatible.
- Para una autenticación real, el resultado de WebAuthn debe validarse en servidor.
- WebAuthn trabaja con **credenciales de clave pública** y tiene dos ceremonias: registro y autenticación.

## Requisitos
- PHP 8+
- Navegador moderno con soporte WebAuthn

## Ejecutar rápido
```bash
php -S localhost:8000
```

Luego abre:

```text
http://localhost:8000/index.php
```

En un teléfono real, usa tu dominio HTTPS.
