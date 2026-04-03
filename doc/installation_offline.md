# Procédure d'Installation des Ressources Hors Ligne (Offline)

Ce document détaille les étapes techniques réalisées pour rendre l'application Frontend 100% autonome (sans dépendance Internet), notamment pour les polices d'écriture (Roboto) et les icônes (Material Icons).

## 1. Téléchargement des Ressources

Nous avons utilisé des commandes PowerShell pour télécharger les fichiers de police directement depuis les serveurs de Google (CDN) vers le système de fichiers local.

### Commandes PowerShell utilisées :

```powershell
# 1. Création de la structure de dossiers temporaire dans src/assets
# Le paramètre -Force permet de créer les dossiers parents si nécessaire sans erreur
mkdir "src\assets\fonts\roboto" -Force
mkdir "src\assets\fonts\material-icons" -Force
mkdir "src\assets\css" -Force

# 2. Téléchargement du fichier de police pour les Icônes (Material Icons)
# Invoke-WebRequest télécharge le fichier depuis l'URL (-Uri) vers le chemin local (-OutFile)
Invoke-WebRequest -Uri "https://fonts.gstatic.com/s/materialicons/v145/flUhRq6tzZclQEJ-Vdg-IuiaDsNZ.ttf" -OutFile "src\assets\fonts\material-icons\MaterialIcons-Regular.ttf"

# 3. Téléchargement de la police d'écriture Roboto (3 graisses différentes)
# Version Light (300)
Invoke-WebRequest -Uri "https://fonts.gstatic.com/s/roboto/v50/KFOMCnqEu92Fr1ME7kSn66aGLdTylUAMQXC89YmC2DPNWuaabWmT.ttf" -OutFile "src\assets\fonts\roboto\Roboto-Light.ttf"
# Version Regular (400 - Standard)
Invoke-WebRequest -Uri "https://fonts.gstatic.com/s/roboto/v50/KFOMCnqEu92Fr1ME7kSn66aGLdTylUAMQXC89YmC2DPNWubEbWmT.ttf" -OutFile "src\assets\fonts\roboto\Roboto-Regular.ttf"
# Version Medium (500 - Gras)
Invoke-WebRequest -Uri "https://fonts.gstatic.com/s/roboto/v50/KFOMCnqEu92Fr1ME7kSn66aGLdTylUAMQXC89YmC2DPNWub2bWmT.ttf" -OutFile "src\assets\fonts\roboto\Roboto-Medium.ttf"
```

## 2. Migration vers le dossier Public

L'application Angular est configurée (dans `angular.json`) pour servir les fichiers statiques depuis le dossier `public/` et non `src/assets/`. Il a donc fallu déplacer les fichiers téléchargés.

### Script de migration :

```powershell
$src = "src\assets"
$dest = "public\assets"

# Vérifie si le dossier source existe
if (Test-Path $src) {
    # Crée le dossier destination (public/assets) s'il n'existe pas
    if (!(Test-Path $dest)) { New-Item -ItemType Directory -Force -Path $dest | Out-Null }
    
    # Parcourt et déplace chaque sous-dossier (fonts, css) vers public/assets
    Get-ChildItem $src | ForEach-Object {
        $target = Join-Path $dest $_.Name
        # Si le dossier existe déjà, on fusionne le contenu
        if (Test-Path $target) {
            Move-Item (Join-Path $_.FullName "*") $target -Force
            Remove-Item $_.FullName -Force -Recurse
        } else {
            # Sinon on déplace le dossier entier
            Move-Item $_.FullName $dest -Force
        }
    }
}
```

## 3. Création des Fichiers CSS Locaux

Pour que l'application utilise ces fichiers locaux plutôt que les liens Internet, nous avons créé deux fichiers CSS dans `public/assets/css/`.

### Fichier `public/assets/css/material-icons.css`
Ce fichier définit la "font-face" pour les icônes, en pointant vers le fichier local `.ttf`.

```css
@font-face {
  font-family: 'Material Icons';
  font-style: normal;
  font-weight: 400;
  /* Chemin relatif vers le dossier fonts parent */
  src: url('../fonts/material-icons/MaterialIcons-Regular.ttf') format('truetype');
}

.material-icons {
  font-family: 'Material Icons';
  font-weight: normal;
  font-style: normal;
  font-size: 24px;
  line-height: 1;
  letter-spacing: normal;
  text-transform: none;
  display: inline-block;
  white-space: nowrap;
  word-wrap: normal;
  direction: ltr;
}
```

### Fichier `public/assets/css/roboto.css`
Même principe pour la police d'écriture Roboto.

```css
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 300;
  src: url('../fonts/roboto/Roboto-Light.ttf') format('truetype');
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url('../fonts/roboto/Roboto-Regular.ttf') format('truetype');
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 500;
  src: url('../fonts/roboto/Roboto-Medium.ttf') format('truetype');
}
```

## 4. Intégration dans l'Application

La dernière étape consiste à modifier le fichier principal `src/index.html` pour ne plus appeler Google Fonts mais utiliser nos fichiers locaux.

**Avant (Links Google CDN) :**
```html
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
```

**Après (Liens Locaux) :**
```html
<link href="assets/css/roboto.css" rel="stylesheet">
<link href="assets/css/material-icons.css" rel="stylesheet">
```

---
**Note pour la maintenance** : Si vous devez ajouter d'autres ressources externes (polices, scripts JS), suivez la même logique : téléchargez les fichiers dans `public/assets/` et faites-y référence dans `index.html`.
