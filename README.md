# CafePOS POS

Point of sale and owner dashboard for the CafePOS cafe.

- **Cashier screen** at `/pos`: tap products, take the order, done. No login.
- **Owner dashboard** at `/admin`: orders, stock, purchases, expenses, products and recipes,
  monthly report. PIN login (default `1234`).

Stack: Laravel 13, Filament v5, Livewire, SQLite. Runs fully offline on one Windows laptop;
the owner opens the dashboard from his phone over the cafe Wi-Fi.

## Development

Requires PHP 8.3+, Composer and Node.

```bash
composer install
npm install
npm run build              # or: npm run dev
cp .env.example .env
php artisan key:generate
php artisan migrate --seed # creates database/database.sqlite if asked
php artisan serve
```

`OWNER_PIN` in `.env` sets the owner PIN **when the owner account is first created** (default `1234`).
After that, change it from the dashboard ("Changer le PIN").

Tests and formatting:

```bash
php artisan test
vendor/bin/pint --dirty
```

## Deploying to the cafe laptop

The laptop needs nothing installed: the package ships a portable PHP, `vendor/` and the built assets.

On the development machine:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\get-php.ps1   # portable PHP 8.3 -> deploy\php
powershell -ExecutionPolicy Bypass -File deploy\package.ps1   # builds assets, writes ..\cafepos-release
```

Copy the `cafepos-release` folder to a USB stick, then onto the laptop's local disk
(e.g. `C:\CafePOS`; **not** a OneDrive/Dropbox synced folder). On the laptop, right-click
`deploy\setup.bat` -> **Run as administrator**. It:

1. creates `.env` (production mode), the app key and a fresh database with the menu/recipes;
2. opens TCP port 8000 in the firewall (rule "CafePOS POS");
3. starts the app automatically at logon (minimized window);
4. schedules a nightly backup at 23:30;
5. puts a **Caisse CafePOS** shortcut on the desktop;
6. prints the address for the owner's phone.

`deploy\setup-app.bat` is the app-only part (no admin rights, no system changes). Both are safe
to re-run: the existing `.env`, app key and database are kept. If you move the folder, run
`deploy\setup-app.bat` again (it refreshes the cached paths).

### Daily use

- Start: automatic at logon, or double-click `deploy\start-cafepos.bat`. Keep its window open.
- Cashier: the **Caisse CafePOS** desktop shortcut (`http://localhost:8000/pos`).
- Owner, from the phone on the same Wi-Fi: `http://<laptop-IP>:8000/admin` (setup prints it).
  - Give the laptop a fixed address in the router (**DHCP reservation**), otherwise the IP
    and the phone bookmark can change.
  - Disable sleep while the cafe is open (Settings -> System -> Power -> Sleep: Never when
    plugged in), otherwise the phone loses access.

### Backups

- `php artisan app:backup` (or `deploy\backup.bat`) writes
  `storage\backups\cafepos-YYYY-MM-DD-HHmmss.sqlite` (UTC time) using SQLite `VACUUM INTO`,
  safe while the app is running, and keeps the newest 30.
- Runs automatically every night at 23:30 (Task Scheduler, "CafePOS POS Backup").
- Occasionally copy `storage\backups` to a USB stick: a backup on the same disk does not survive
  a dead laptop.
- To restore: stop the app, copy a backup over `database\database.sqlite`, start the app.

### Owner tasks

- **Change the PIN**: dashboard -> "Changer le PIN".
- **Milk quantities**: drinks with milk are seeded with an *unknown* milk quantity (their cost
  shows as an estimate). Fill it in: Products -> open the drink -> recipe -> Lait quantity.
- **Re-seeding never overwrites his edits**: setup and `migrate --seed` only add missing
  items (prices, recipes, stock and PIN he changed are kept).

SQLite runs in WAL mode with a 5 s busy timeout (`config/database.php`), so the cashier and the
phone can use the app at the same time.

## Pour le patron (en français)

**Démarrer l'application** : elle démarre toute seule quand on allume l'ordinateur et qu'on
se connecte. Sinon, double-cliquez sur `deploy\start-cafepos.bat`. Une fenêtre noire
"CafePOS POS" s'ouvre : **ne la fermez pas** pendant que le café est ouvert.

**Caisse** : double-cliquez sur l'icône **Caisse CafePOS** sur le bureau
(ou ouvrez `http://localhost:8000/pos` dans le navigateur).

**Tableau de bord sur le téléphone** : le téléphone doit être connecté au Wi-Fi du café.
Ouvrez `http://<adresse-de-l-ordinateur>:8000/admin` (l'adresse, du type `192.168.1.20`,
est affichée à la fin de l'installation), puis entrez votre PIN (1234 au départ, changez-le
dans "Changer le PIN"). Ajoutez la page aux favoris.

**Sauvegardes** : faites automatiquement chaque nuit dans `storage\backups`. De temps en temps,
copiez ce dossier sur une clé USB.
