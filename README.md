# Vite & Gourmand

C' est une application web développée pour une entreprise de traiteur gérée par Julie et José à Bordeaux depuis 25 ans. L'objectif est de présenter leurs menus en constante évolution et de permettre la prise de commande en ligne.

## 1. Prerequis

- WampServer avec Apache, PHP et MySQL/MariaDB
- PHP 8.2 ou plus recent recommande
- phpMyAdmin

## 2. Placer le projet

Le dossier du projet doit etre dans le dossier `www` de WampServer :

```text
C:\wamp64\www\vite-et-gourmand
```

La page d'accueil locale sera ensuite accessible ici :

```text
http://localhost/vite-et-gourmand/
```

## 3. Activer les URL propres

Le projet utilise le fichier `.htaccess` pour avoir des URL sans `.php`, par exemple :

```text
http://localhost/vite-et-gourmand/menus
```

Dans WampServer, verifier que le module Apache `rewrite_module` est active :

1. Clic gauche sur l'icone WampServer.
2. Aller dans `Apache`.
3. Aller dans `Apache modules`.
4. Cocher `rewrite_module` si ce n'est pas deja fait.
5. Redemarrer WampServer.

Si les liens redirigent toujours vers l'accueil ou ne fonctionnent pas, verifier que Apache autorise les `.htaccess` avec `AllowOverride All`.

## 4. Creer la base de donnees

Ouvrir phpMyAdmin :

```text
http://localhost/phpmyadmin
```

Creer une base de donnees :

```text
vite_et_gourmand
```

Ensuite :

1. Cliquer sur la base `vite_et_gourmand`.
2. Aller dans l'onglet `Importer`.
3. Choisir le fichier `database.sql`.
4. Lancer l'import.

Apres import, verifier que les tables principales existent :

- `utilisateur`
- `menu`
- `menu_image`
- `plat`
- `allergene`
- `menu_plat`
- `plat_allergene`
- `commande`
- `commande_statut_historique`
- `avis`
- `horaire`
- `password_reset`
- `login_attempt`
- `email_log`

## 5. Configuration MySQL locale

Par defaut, le fichier `includes/db.php` utilise cette configuration locale :

```text
DB_HOST=localhost
DB_PORT=3306
DB_NAME=vite_et_gourmand
DB_USER=root
DB_PASSWORD=
```

Avec WampServer, l'utilisateur MySQL par defaut est souvent :

```text
root
```

Et le mot de passe est souvent vide.

Si votre configuration MySQL est differente, modifier les variables d'environnement ou adapter temporairement la configuration locale.

## 6. Configuration MongoDB locale

Le projet peut fonctionner sans MongoDB local grace au fichier fallback JSON dans `data/`.

Pour utiliser un vrai MongoDB en local ou MongoDB Atlas :

1. Copier le fichier :

```text
includes\mongodb_config.example.php
```

1. Le renommer en :

```text
includes\mongodb_config.php
```

1. Renseigner les valeurs MongoDB :

```php
define('MONGODB_URI', 'mongodb+srv://USER:PASSWORD@cluster.mongodb.net/?retryWrites=true&w=majority');
define('MONGODB_DATABASE', 'vite_et_gourmand');
define('MONGODB_COLLECTION', 'statistiques_menus');
```

## 7. Configuration email locale

Les emails sont utilises pour le contact, la creation de compte employe et la reinitialisation de mot de passe.

En local, l'envoi d'email peut ne pas fonctionner. L'application reste utilisable, mais les emails reels peuvent ne pas partir.

## 8. Lancer l'application

Demarrer WampServer.

Quand l'icone WampServer est verte, ouvrir :

```text
http://localhost/vite-et-gourmand/
```

Tester ensuite les pages :

```text
http://localhost/vite-et-gourmand/menus
http://localhost/vite-et-gourmand/contact
http://localhost/vite-et-gourmand/login
http://localhost/vite-et-gourmand/register
```

## 9. Tester les roles

Creer ou utiliser un compte administrateur depuis la base de donnees.

## 10. Tester l'upload des images de menus

Se connecter avec un compte admin ou employe.

Aller dans :

```text
http://localhost/vite-et-gourmand/espace_employe
```

Dans la section `Gestion des Menus` :

1. Ajouter un menu.
2. Choisir une image principale.
3. Ajouter eventuellement plusieurs images de galerie.
4. Enregistrer.

Les fichiers sont stockes dans :

```text
assets\images
```

Les noms des fichiers sont enregistres en base dans :

- `menu_image` pour l'image principale

Formats acceptes :

- JPG
- PNG
- WEBP
- AVIF

Taille maximale :

```text
5 Mo
```

## 11. Problemes frequents

### Erreur de connexion a la base

Message possible :

```text
Erreur serveur. Impossible de se connecter a la base de donnees.
```

Verifier :

- WampServer est lance
- MySQL est lance
- la base `vite_et_gourmand` existe
- le fichier `database.sql` a ete importe
- l'utilisateur MySQL et le mot de passe sont corrects

### Les URL sans `.php` ne fonctionnent pas

Verifier :

- le fichier `.htaccess` existe
- `rewrite_module` est active dans Apache
- WampServer a ete redemarre
- Apache autorise `AllowOverride All`

### Les images ne s'enregistrent pas

Verifier :

- `upload_max_filesize` est superieur a la taille de l'image
- `post_max_size` est superieur a `upload_max_filesize`
- le dossier `assets/images` existe
- le format envoye est JPG, PNG, WEBP ou AVIF
