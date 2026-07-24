# Configuration Google Tag Manager

## 1. Créer la balise GA4
- Va sur tagmanager.google.com
- Ouvre le conteneur GTM-PHQB9TMP
- Nouvelle balise → Google Analytics : événement GA4
- Type : Configuration GA4
- ID de mesure : G-XXXXXXXXXX (ton ID Analytics)
- Déclencheur : All Pages
- Nom : GA4 - Configuration
- Enregistre

## 2. Créer la balise événements personnalisés
- Nouvelle balise → Google Analytics : événement GA4
- Type : Événement GA4
- Balise de configuration : sélectionne "GA4 - Configuration"
- Nom de l'événement : {{Event}}
- Déclencheur : Custom Event → Nom de l'événement : .* (regex)
- Nom : GA4 - Événements personnalisés
- Enregistre

## 3. Publier
- Clique sur Envoyer en haut à droite
- Nom de version : "Tracking clics Marine Bernard"
- Publie

## Événements trackés
- choix_univers : quel panneau l'utilisateur choisit sur accueil.html
- clic_projet : quel projet est cliqué
- telecharger_cv : téléchargements du CV
- clic_contact : clics email et téléphone
- clic_reseau_social : clics Facebook Instagram Komoot
- switch_univers : passages entre univers pro et rando
- clic_photo : photos cliquées dans la galerie
- page_vue : chaque page visitée avec l'univers détecté
