# Guide d'Installation Serveur Client & Mises à Jour à Distance (SYGES)

Ce guide détaille pas à pas toutes les opérations à réaliser **lors de la livraison sur le serveur du client** afin que le bouton **« Mise à jour »** fonctionne en **1 clic**, **sans jamais demander de mot de passe**, et en toute sécurité pour votre compte GitHub.

---

## 1. Principe de fonctionnement & Sécurité

1. **Pas de Node.js requis chez le client** : le dossier compilé `public/build` est désormais versionné dans Git. Le serveur client télécharge directement l'interface déjà compilée.
2. **Tags SemVer validés uniquement (`vX.Y.Z`)** :
   - Si vous faites un simple `git push origin main` (travail en cours non tagué), le serveur client **ne le considère pas** comme une version téléchargeable.
   - Seul un tag officiel au format **`vMAJEUR.MINEUR.PATCH`** déclenche une mise à jour chez le client :
     - **Majeure (`v2.0.0`)** : refontes globales et fonctionnalités lourdes du système.
     - **Mineure (`v1.1.0`)** : nouvelles fonctionnalités métier demandées par l'école.
     - **Patch (`v1.0.1`)** : corrections de bugs et optimisations rapides.
3. **Clé SSH de déploiement en lecture seule (*Deploy Key*)** :
   - Vous **n'avez pas besoin** de connecter votre compte personnel GitHub complet sur la machine du client.
   - Vous générez une clé SSH **sans mot de passe** rattachée uniquement au dépôt SYGES en **lecture seule**. Ainsi, l'application télécharge les mises à jour automatiquement sans fenêtre de mot de passe, et personne sur le serveur client ne peut modifier votre code sur GitHub.

---

## 2. Préparation du Serveur Client (Windows / Laragon)

### Étape 2.1 — Installer Git pour Windows

1. Téléchargez et installez **Git for Windows** (`https://git-scm.com/download/win`) si ce n'est pas déjà fait.
2. Pendant l'installation, conservez l'option **« Git from the command line and also from 3rd-party software »** afin que `git` soit présent dans le `PATH` global de Windows.
3. Vérifiez dans un terminal PowerShell :
   ```powershell
   git --version
   ```

### Étape 2.2 — Autoriser le dossier du projet dans Git (`safe.directory`)

Pour éviter que Git ne bloque l'exécution depuis le serveur web Apache/Laragon pour cause de droits Windows, exécutez dans PowerShell (en Administrateur si possible) :

```powershell
git config --global --add safe.directory C:/laragon/www/SYGES
git config --global core.autocrlf false
git config --global core.fileMode false
```

*(Adaptez `C:/laragon/www/SYGES` si le dossier d'installation porte un autre chemin).*

### Étape 2.3 — Ajouter PHP de Laragon au `PATH` Windows

1. Ouvrez **Laragon**.
2. Cliquez sur **Menu -> Outils (Tools) -> Path -> Ajouter Laragon au Path (Add Laragon to Path)**.
3. Redémarrez Laragon pour qu'Apache prenne en compte le `PATH` de `git` et `php`.

---

## 3. Configurer l'accès GitHub sans mot de passe (Clé SSH Deploy Key)

> **Important** : Exécutez ces commandes dans la session Windows qui fait tourner **Laragon** sur le serveur du client.

### Étape 3.1 — Générer une clé SSH dédiée SANS mot de passe (`passphrase` vide)

Ouvrez PowerShell sur le serveur client et exécutez :

```powershell
ssh-keygen -t ed25519 -C "syges-serveur-client" -f "$HOME\.ssh\id_ed25519" -N '""'
```

*(Si PowerShell demande de confirmer ou si vous tapez simplement `ssh-keygen -t ed25519 -C "syges-serveur-client"`, appuyez sur **Entrée** à chaque question **sans saisir de mot de passe**).*

### Étape 3.2 — Copier la clé publique

Affichez la clé publique générée :

```powershell
Get-Content "$HOME\.ssh\id_ed25519.pub"
```

Copiez toute la ligne qui commence par `ssh-ed25519 AAAA...`.

### Étape 3.3 — Déclarer la clé sur votre dépôt GitHub

Depuis votre navigateur (ou votre propre ordinateur) :

1. Ouvrez votre dépôt **SYGES** sur GitHub.
2. Allez dans **Settings** -> **Deploy keys** (dans le menu de gauche) -> **Add deploy key**.
3. **Title** : `Serveur Client École - SYGES`
4. **Key** : collez la clé publique `ssh-ed25519 ...`
5. **Allow write access** : **NE PAS COCHER** (lecture seule = sécurité maximale).
6. Cliquez sur **Add key**.

### Étape 3.4 — Valider l'empreinte SSH une première fois sur le serveur client

Sur le serveur client, lancez dans PowerShell :

```powershell
ssh -T git@github.com
```

- Lorsqu'il demande `Are you sure you want to continue connecting (yes/no/[fingerprint])?`, tapez **`yes`** puis **Entrée**.
- Vous devez obtenir le message :
  `Hi <votre-compte>/SYGES! You've successfully authenticated, but GitHub does not provide shell access.`

### Étape 3.5 — Basculer le dépôt SYGES du client en URL SSH

Dans le dossier du projet sur le serveur client (`cd C:\laragon\www\SYGES`) :

```powershell
cd C:\laragon\www\SYGES

# Vérifier l'URL actuelle
git remote -v

# Configurer l'URL SSH (remplacez VOTRE_COMPTE et VOTRE_DEPOT par les vôtres)
git remote set-url origin git@github.com:VOTRE_COMPTE/VOTRE_DEPOT.git

# Tester que le téléchargement des tags fonctionne sans rien demander
git fetch origin --tags --force
```

Si `git fetch origin --tags --force` se termine sans poser aucune question, le serveur est **100 % prêt** !

---

## 4. Configurer l'envoi gratuit des sauvegardes `.SQL` par Email (`teguiasimo@gmail.com`)

Avant chaque mise à jour, SYGES crée automatiquement un fichier `.sql` dans `storage/app/backups/` et l'expédie à **`teguiasimo@gmail.com`**.

Pour activer cet envoi gratuit via Gmail dans le fichier `.env` du serveur client :

1. Sur un compte Gmail d'expédition (ou le vôtre), activez la **Validation en deux étapes**, puis créez un **Mot de passe d'application** (`Compte Google -> Sécurité -> Mots de passe des applications`, 16 lettres).
2. Renseignez ces lignes dans le fichier `.env` du serveur client :

```env
APP_VERSION=1.0.0
DEVELOPER_NOTIFICATION_EMAIL="teguiasimo@gmail.com"

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME="votre.adresse.expediteur@gmail.com"
MAIL_PASSWORD="xxxx xxxx xxxx xxxx"
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="votre.adresse.expediteur@gmail.com"
MAIL_FROM_NAME="SYGES - Serveur Client"
```

> **Note de tolérance aux pannes** : Si le client n'a pas configuré de SMTP ou si le port mail est bloqué par son opérateur Internet, **la sauvegarde `.sql` locale est quand même créée sur le disque** et la mise à jour n'est jamais bloquée. Le Super Admin peut aussi télécharger le fichier `.sql` en un clic depuis l'interface.

---

## 5. Votre Workflow Développeur pour publier une Mise à Jour à distance

Chaque fois que le client vous soumet un besoin ou un correctif :

### Chez vous (poste développeur)

1. Codez la fonctionnalité ou le correctif (ainsi que les nouvelles migrations éventuelles).
2. **Compilez obligatoirement le frontend** pour mettre à jour `public/build` :
   ```powershell
   npm run build
   ```
3. Commitez vos changements (y compris `public/build`) :
   ```powershell
   git add .
   git commit -m "feat: ajout de la nouvelle fonctionnalité demandée"
   ```
4. **Créez un tag SemVer validé** à l'aide de la commande intégrée SYGES :
   - Pour une **correction de bug (Patch `v1.0.1`)** :
     ```powershell
     php artisan syges:release patch -m "Correction calcul d'assiduité et affichage PDF"
     ```
   - Pour une **nouvelle fonctionnalité (Mineure `v1.1.0`)** :
     ```powershell
     php artisan syges:release minor -m "Ajout du nouveau rapport trimestriel demandé"
     ```
   - Pour une **évolution lourde / majeure (Majeure `v2.0.0`)** :
     ```powershell
     php artisan syges:release major -m "Nouveau module complet de comptabilité analytique"
     ```
   *(Vous pouvez ajouter `--dry-run` pour prévisualiser le numéro de version avant de créer le tag).*
5. **Poussez le code et le tag vers GitHub** :
   ```powershell
   git push origin main --tags
   ```

> **Initialisation de la toute première version avant livraison samedi** :
> Une fois prêt à livrer, lancez `npm run build`, commitez, puis créez le tag socle `git tag -a v1.0.0 -m "Livraison initiale SYGES v1.0.0"` et `git push origin main --tags`.

### Chez le client (ou par instruction téléphonique)

1. Connecter la machine serveur à Internet (Wi-Fi / 4G).
2. Se connecter à SYGES avec le compte **Super Administrateur** :
   - **Identifiant** : `superadmin`
   - **Mot de passe** : `5idfasuper`
3. Aller dans **Administration -> Mise à jour & Versions** (`/administration/system-update`).
4. Cliquer sur **Rechercher une mise à jour**, puis sur **Mettre à jour** et confirmer.
5. Une fois la notification verte affichée en haut à droite, cliquer sur **Actualiser la page maintenant** (`F5`).

---

## 6. Récapitulatif des Comptes & Rôles

| Compte / Rôle | Identifiant | Mot de passe initial | Droits & Périmètre |
| :--- | :--- | :--- | :--- |
| **Super Administrateur** (`super-admin`) | `superadmin` | `5idfasuper` | Accès total (100 % des permissions), gestion multi-établissements, gestion des rôles et accès exclusif au module **Mise à jour & Versions**. |
| **Administrateur** (`admin`) | *(ex: `administrateur`)* | *(défini par l'école)* | Responsable local de l'établissement : gestion courante pédagogique, financière et RH, **sans** accès aux mises à jour système, **sans** modification des rôles RBAC et **sans** suppressions destructives critiques (ex: impossibilité de supprimer définitivement un élève). |

