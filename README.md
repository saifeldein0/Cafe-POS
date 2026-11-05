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
3. schedules a nightly backup at 23:30;
4. puts a **Caisse CafePOS** icon on the desktop (and removes the auto-start shortcut and the
   `.url` shortcut that older versions created);
5. prints the address for the owner's phone.

There is **no auto-start**: the app runs only after someone double-clicks the desktop icon.

`deploy\setup-app.bat` is the app-only part (no admin rights, no system changes). Both are safe
to re-run: the existing `.env`, app key and database are kept. If you move the folder, run
`deploy\setup.bat` again (it refreshes the cached paths and points the icon at the new folder).

### Daily use

- **Start**: double-click **Caisse CafePOS** on the desktop. It starts the server in the
  background (no window) if it is not already running, waits until it answers (up to ~30 s,
  otherwise a message box explains what to do) and opens `http://localhost:8000/pos` in the
  default browser. Double-clicking again just reopens the till; it never starts a second server.
- **Stop**: the server stops when the owner logs off, restarts or shuts down the laptop.
  Next time, double-click the icon again. (Power users can run `deploy\start-cafepos.bat`
  instead: the server then runs in a visible console window, and closing that window stops it.)
- Owner, from the phone on the same Wi-Fi: `http://<laptop-IP>:8000/admin`.
  - The phone only works while the laptop is on **and** the app has been started with the icon.
  - The laptop IP is given by the router, so it can differ from cafe to cafe and can change.
    Setup prints the current one; give the laptop a fixed address in the router
    (**DHCP reservation**) so the phone bookmark never breaks.
  - Disable sleep while the cafe is open (Settings -> System -> Power -> Sleep: Never when
    plugged in), otherwise the phone loses access.
- How the icon works: shortcut -> `conhost.exe --headless` -> hidden PowerShell ->
  `deploy\launch-cafepos.ps1` -> `deploy\start-cafepos.bat` (hidden). Launcher messages go
  to `storage\logs\launcher.log`. `CAFEPOS_PORT` changes the port for both scripts;
  `CAFEPOS_NO_BROWSER=1` makes the launcher skip opening the browser (testing).

### Backups

- `php artisan app:backup` (or `deploy\backup.bat`) writes
  `storage\backups\cafepos-YYYY-MM-DD-HHmmss.sqlite` (UTC time) using SQLite `VACUUM INTO`,
  safe while the app is running, and keeps the newest 30.
- Runs automatically every night at 23:30 (Task Scheduler, "CafePOS POS Backup") **if the
  laptop is on** at that time (the app does not need to be started). If the laptop is off,
  that night is skipped; the next backup happens the following night.
- Occasionally copy `storage\backups` to a USB stick: a backup on the same disk does not survive
  a dead laptop.
- To restore: restart the laptop (this stops the app) and do not start it yet, copy a backup
  over `database\database.sqlite`, then double-click the desktop icon.

### Owner tasks

- **Change the PIN**: dashboard -> "Changer le PIN".
- **Milk quantities**: drinks with milk are seeded with an *unknown* milk quantity (their cost
  shows as an estimate). Fill it in: Products -> open the drink -> recipe -> Lait quantity.
- **Re-seeding never overwrites his edits**: setup and `migrate --seed` only add missing
  items (prices, recipes, stock and PIN he changed are kept).

SQLite runs in WAL mode with a 5 s busy timeout (`config/database.php`), so the cashier and the
phone can use the app at the same time.

## Pour le patron (en français)

**Pour démarrer** : double-cliquez sur l'icône **Caisse CafePOS** du bureau. La caisse s'ouvre
dans le navigateur après quelques secondes. Il n'y a pas de fenêtre noire. Refaites-le après
chaque redémarrage de l'ordinateur (l'application ne démarre pas toute seule). Si vous
double-cliquez alors qu'elle tourne déjà, la caisse s'ouvre simplement à nouveau.

**Arrêter** : rien à faire. L'application s'arrête quand vous éteignez l'ordinateur ou fermez
votre session.

**Tableau de bord sur le téléphone** : le téléphone doit être connecté au Wi-Fi du café, et
l'ordinateur doit être allumé avec la caisse démarrée (icône Caisse CafePOS). Ouvrez
`http://<adresse-de-l-ordinateur>:8000/admin` (l'adresse, du type `192.168.1.20`, dépend de la
box et est affichée à la fin de l'installation), puis entrez votre PIN (1234 au départ,
changez-le dans "Changer le PIN"). Ajoutez la page aux favoris. Pour que cette adresse ne change
jamais, demandez à fixer l'adresse de l'ordinateur dans la box (réservation DHCP).

**Sauvegardes** : faites automatiquement chaque nuit à 23h30 dans `storage\backups`, si
l'ordinateur est allumé à cette heure-là. De temps en temps, copiez ce dossier sur une clé USB.
