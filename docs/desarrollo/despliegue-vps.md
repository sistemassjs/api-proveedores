# Despliegue automático al VPS — api-proveedores

Cada push a `main` entra por SSH como `deploy`, actualiza `/var/www/api_proveedores` y deja la API en `https://api.rorisafe.com/gestion`. No hay disparo manual. El `.env`, MySQL y archivos privados se quedan en el servidor.

Archivo del workflow: `.github/workflows/deploy-vps.yml`.

El frontend (`app-proveedores`) se publica en GoDaddy, no en este VPS.

## Arquitectura

```text
GitHub Actions (push a main)
  --SSH llave github_actions_deploy-->  deploy@<VPS_HOST>
                                         /var/www/api_proveedores
  git fetch origin main  --SSH llave id_ed25519_github-->  github.com/...
```

Hay **dos llaves distintas**:

| Llave | Quién la usa | Para qué |
|---|---|---|
| `/home/deploy/.ssh/github_actions_deploy` | GitHub Actions → VPS | Entrar como `deploy` |
| `/home/deploy/.ssh/id_ed25519_github` | VPS → GitHub | `git fetch` / `git pull` sin token |

Si el mismo VPS ya despliega otra API (p. ej. Colibrí), **reutiliza** el usuario `deploy` y la llave Actions; solo cambia `VPS_APP_DIR`.

No se usa PAT ni prompt `Username for 'https://github.com'` en el autodépliegue.

## Secretos de GitHub Actions

Repo de esta API → Settings → Secrets and variables → Actions:

| Secreto | Valor |
|---|---|
| `VPS_HOST` | IP del VPS (no el FQDN) |
| `VPS_USER` | `deploy` |
| `VPS_PORT` | `22` |
| `VPS_SSH_KEY` | Contenido completo de `/home/deploy/.ssh/github_actions_deploy` (`BEGIN`/`END` incluidos) |
| `VPS_APP_DIR` | `/var/www/api_proveedores` |

```bash
sudo cat /home/deploy/.ssh/github_actions_deploy
```

## Preparación en el VPS (una vez)

### Permisos del proyecto

```bash
chown -R deploy:www-data /var/www/api_proveedores
find /var/www/api_proveedores/storage /var/www/api_proveedores/bootstrap/cache -type d -exec chmod 2775 {} \;
find /var/www/api_proveedores/storage /var/www/api_proveedores/bootstrap/cache -type f -exec chmod 664 {} \;
chmod 640 /var/www/api_proveedores/.env
```

No hacer `git pull` como **root** en esta carpeta (rompe `.git/objects`). Usar siempre `deploy`:

```bash
sudo -u deploy -H git -C /var/www/api_proveedores pull
```

### Remoto Git por SSH

```bash
cd /var/www/api_proveedores
sudo -u deploy -H git remote set-url origin git@github.com:ORG/api-proveedores.git
# Ajusta ORG/repo al remoto real del proyecto
sudo -u deploy -H git remote -v

sudo -u deploy -H bash -lc '
cd /var/www/api_proveedores
ssh -T git@github.com
git fetch origin main
'
```

Si falla la llave hacia GitHub o `known_hosts`, copiar a `deploy` la misma configuración SSH de cuenta que usan los otros deploys del VPS.

### Llave Actions → VPS (solo si no existe)

```bash
sudo -u deploy -H bash -lc '
mkdir -p ~/.ssh
chmod 700 ~/.ssh
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_deploy -N ""
cat ~/.ssh/github_actions_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys ~/.ssh/github_actions_deploy
'

sudo -u deploy -H ssh -i /home/deploy/.ssh/github_actions_deploy \
  -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=yes \
  deploy@127.0.0.1 'whoami && hostname'
```

## Qué hace el workflow

1. SSH al VPS con `VPS_*`
2. `php artisan down --retry=60` (si falla, el `trap` hace `up`)
3. `git fetch origin main` + `git reset --hard origin/main`
4. `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`
5. `php artisan migrate --force --no-interaction`
6. `optimize:clear`, `config:cache`, `route:cache`, `view:cache`
7. Asegura `public/storage` como **symlink** (`storage:link`):
   - Si existe como carpeta real (p. ej. por un `.gitkeep` viejo), la elimina
   - Si el symlink ya está, no lo recrea
   - Falla el deploy si al final no es un enlace simbólico
8. `php artisan up`
9. `GET https://api.rorisafe.com/gestion/api/status` desde el runner

No ejecuta seeders, `migrate:fresh`, `key:generate` ni modifica `.env`.

**Importante:** los archivos públicos viven en `storage/app/public/`. `public/storage` solo es el enlace; no debe versionarse ni crearse como directorio.

## Versión / verificación

La versión de la API está en código:

`app/Http/Controllers/ApiStatusController.php` → `public const VERSION`

Tras un deploy:

1. Actions → job **Desplegar a VPS** en verde
2. Abrir `https://api.rorisafe.com/gestion/api/status`
3. Comprobar `data.version`

## Fallos frecuentes

| Síntoma | Causa / solución |
|---|---|
| `dial tcp …: i/o timeout` | GitHub Actions **no alcanza** el VPS por SSH (antes de ejecutar el script). Revisar: VPS encendido, `VPS_HOST`/`VPS_PORT` correctos, firewall/UFW/security group con puerto SSH abierto a Internet (los runners de GitHub usan IPs dinámicas), Fail2ban no bloqueó la IP del runner, proveedor sin caída. Probar desde tu PC: `ssh -p PORT deploy@HOST`. Reintentar con Actions → **Run workflow** tras corregir red. |
| `could not read Username for 'https://github.com'` | Remoto HTTPS → `git remote set-url` a SSH |
| `Host key verification failed` | `deploy` sin `github.com` en `known_hosts` |
| `Permission denied (publickey)` hacia GitHub | Llave de cuenta no en GitHub o no en home de `deploy` |
| `insufficient permission … .git/objects` | `git` hecho como root → `chown -R deploy:www-data` del proyecto |
| Job OK pero versión vieja | No se subió `VERSION` o caché de CDN/proxy; hard refresh / curl directo |
| Logos /storage 404 tras deploy | `public/storage` era carpeta, no symlink; el workflow ya lo corrige; en VPS: `rm -rf public/storage && php artisan storage:link` |

## Límites

- No toca el front en GoDaddy.
- CORS, Reverb, `APP_KEY` y MySQL viven en el `.env` del VPS.
- Recargar PHP-FPM no forma parte de este flujo.
