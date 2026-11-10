# Deploying to staging on cPanel

A private copy of the platform at a staging subdomain (for example `staging.cbsradio.ug`), updated from Git
with one command. It runs in production mode with real-looking data, but payments are manual, SMS and email are
off, and every page carries a striped **STAGING** bar.

What's in this folder:

| File | What it is |
|---|---|
| `deploy.sh` | Checks the server, pulls the branch, installs packages, runs migrations, clears the cache. |
| `staging.env.example` | The `.env` for staging, safe by default. |
| `cron.txt` | The two scheduled jobs. |
| `STAGING-TEST-CHECKLIST.md` | What testers should try, page by page. |

The site's web root must be the repository's `public/` folder. Nothing else (`.env`, `app/`, `vendor/`,
`writable/`) may be reachable from the web.

---

## 0. Before you start (on your computer)

Push the branch you want to test:

```bash
git push origin feature/ui-theme
```

You need, in cPanel: **Terminal** (or SSH access), **MultiPHP Manager**, **MySQL Databases**, **Domains** (or
Subdomains), **Cron Jobs** and **Directory Privacy**. If Terminal is missing, ask the host to enable SSH.

## 1. PHP version and extensions

1. **MultiPHP Manager**: set the staging domain to **PHP 8.2 or newer** (8.3 recommended).
2. **Select PHP Version → Extensions** (or **MultiPHP INI Editor**): make sure `intl`, `mbstring`, `mysqli`,
   `curl`, `fileinfo` and `json` are ticked.
3. In **Terminal**, check which PHP the command line uses:

   ```bash
   php -v
   ls /opt/cpanel/ | grep ea-php        # the versions installed, e.g. ea-php83
   ```

   If `php -v` shows an older version than the site uses, use the full path from now on, e.g.
   `export PHP=/opt/cpanel/ea-php83/root/usr/bin/php` (add that line to `~/.bashrc` to keep it), and type
   `$PHP` wherever these steps say `php`.

## 2. Database

**MySQL Databases**:

1. Create a database, e.g. `cbs_staging` (cPanel names it `<cpaneluser>_cbs_staging`).
2. Create a user, e.g. `cbs_stg`, with a long generated password.
3. **Add user to database** with **All privileges**.

Keep the three full names and the password for step 5.

## 3. Get the code

In **Terminal**:

```bash
cd ~
git clone -b feature/ui-theme https://github.com/<you>/<repo>.git cbs-staging
cd cbs-staging
```

For a private repository, use a deploy key instead of your password:

```bash
ssh-keygen -t ed25519 -C "cbs-staging" -f ~/.ssh/cbs_staging -N ""
cat ~/.ssh/cbs_staging.pub
```

Add the printed key on GitHub under **Repository → Settings → Deploy keys** (read-only), then:

```bash
cat >> ~/.ssh/config <<'CFG'
Host github-cbs
    HostName github.com
    User git
    IdentityFile ~/.ssh/cbs_staging
    IdentitiesOnly yes
CFG
git clone -b feature/ui-theme git@github-cbs:<you>/<repo>.git ~/cbs-staging
```

## 4. Composer

If `composer -V` works, skip this. Otherwise install it for your account only:

```bash
mkdir -p ~/bin
curl -sS https://getcomposer.org/installer | php -- --install-dir="$HOME/bin" --filename=composer.phar
export COMPOSER_BIN="php $HOME/bin/composer.phar"      # add to ~/.bashrc to keep it
```

## 5. The `.env` file

```bash
cd ~/cbs-staging
cp deploy/staging.env.example .env
nano .env
```

Fill in everything in `<...>`: the staging address in `app.baseURL` and `momo.callbackUrl`, the database name,
user and password from step 2, and the mailbox details if you'll test email. Leave
`app.environmentLabel = 'Staging'`, `payments.mode = manual` and both `notifications.*Enabled = false` as they
are unless you are testing those on purpose.

Then lock it down: `chmod 600 .env`.

## 6. Point the subdomain at `public/`

**Domains** (or **Subdomains**) → **Create a new domain**:

- Domain: `staging.<yourdomain>`
- Uncheck "Share document root"
- Document root: `cbs-staging/public`

Then **SSL/TLS Status** → run **AutoSSL** for the new subdomain so `https://` works (the `.env` forces
https).

*If your host won't let a domain's document root sit outside `public_html`:* clone into
`~/cbs-staging` as above, then make the subdomain's document root a symlink to `public/`:

```bash
rm -rf ~/public_html/staging            # only if this empty folder was created by cPanel
ln -s ~/cbs-staging/public ~/public_html/staging
```

and set the subdomain's document root to `public_html/staging`. Never copy the whole project into
`public_html`.

## 7. First deploy

```bash
cd ~/cbs-staging
bash deploy/deploy.sh
```

It stops before changing anything if PHP, an extension, `.env` or Git is not right, and tells you what to fix.
On success it shows the commit that is live.

Then set up the station's roles and a first staff account:

```bash
php spark auth:sync
php spark auth:user:create --email you@yourdomain.ug --role superadmin --verified --generate
```

`--generate` prints a strong password once: keep it. `--verified` matters: staff accounts must have a verified
email to sign in.

Optional, to give testers something to look at: `php spark db:seed DemoSeeder` (5 occasions, packages,
message examples, 2 shows, 4 gifts and a few sample bookings). Skip it if you'll enter real catalogue data.

## 8. Keep it private

**Directory Privacy** → choose `cbs-staging/public` → tick **Password protect this directory**, give it a name
("CBS staging") and create a user and password for testers. Share those with the testers along with their own
staff logins.

This puts a browser password prompt in front of the whole staging site, so neither the public nor search
engines can reach it. (The STAGING label also adds `noindex` to every public page as a second guard.)

## 9. Scheduled jobs

**Cron Jobs**: add the two lines from `deploy/cron.txt`, replacing `CPANELUSER` with your cPanel username and
`php` with the full PHP path if you set one in step 1.

## 10. Check it

Open `https://staging.<yourdomain>/`:

- the striped **STAGING** bar is at the top;
- addresses have no `index.php` in them;
- `https://staging.<yourdomain>/login` signs you in and lands on the admin dashboard;
- `https://staging.<yourdomain>/.env` and `/writable/logs/` return **404 Not Found** (if either shows a file,
  the document root is wrong: fix step 6 before anything else).

Then hand the testers `deploy/STAGING-TEST-CHECKLIST.md`.

---

## Updating staging

After pushing new commits:

```bash
cd ~/cbs-staging && bash deploy/deploy.sh
```

To test a different branch: `bash deploy/deploy.sh some-branch`. Every deploy is recorded in
`writable/logs/deploy.log`.

## When something goes wrong

| Symptom | Look at / fix |
|---|---|
| "Whoops!" or a blank page | `writable/logs/log-<date>.log` (newest at the bottom). |
| 500 error straight away | Folder permissions (`chmod -R u+rwX writable`) or the PHP version in MultiPHP Manager. |
| Pages load but CSS is missing | `app.baseURL` in `.env` must match the address exactly, with `https://` and a trailing `/`. |
| Every page except the homepage is 404 | `app.indexPage = ''` needs Apache's mod_rewrite: check `public/.htaccess` was cloned (hidden file). |
| "Unable to connect to the database" | The full prefixed names in `.env` (`<cpaneluser>_...`), and that the user was added to the database. |
| Can't sign in: "verify your email" | Re-create the account with `--verified`, or set `$requireVerifiedToLogin['email']` to false in `app/Config/Auth.php`. |
| `deploy.sh` stops: "Files tracked by git were changed" | Someone edited files on the server. See them with `git diff`; undo with `git checkout -- .`. |

## Rolling back

```bash
cd ~/cbs-staging
tail -5 writable/logs/deploy.log            # find the previous commit
git checkout <previous-commit>              # the code goes back; data stays
php spark cache:clear
```

Migrations are not undone automatically. They only ever add columns or tables, so older code runs on the newer
database. When the fix is pushed, `bash deploy/deploy.sh feature/ui-theme` returns to the branch.

## Going to production later

The same steps, with a separate folder (`~/cbs-production`), database and domain, and in `.env`:
`app.environmentLabel =` (empty), real payment, SMS and email settings, no Directory Privacy, and deploy from
your release branch (`main`) rather than a feature branch. Take a database backup (**Backup** or
**phpMyAdmin → Export**) before every production deploy.
