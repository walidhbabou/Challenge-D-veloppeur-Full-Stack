# SEC-003 - CORS ouvert à tous les domaines + XSS dans les commentaires

## 📋 Réponses aux Questions

### 1. Comment as-tu testé la vulnérabilité XSS de manière sécurisée ?

J'ai créé un commentaire avec `<script>alert('Vous êtes hacké!')</script>` en environnement local Docker. 

Avant la correction : le script s'exécutait (popup affichée).
Après la correction : le texte s'affiche comme chaîne de caractères sans exécution.

### 2. Pourquoi `dangerouslySetInnerHTML` est-il problématique et quelle est l'alternative ?

`dangerouslySetInnerHTML` insère du HTML brut sans protection, permettant l'exécution de scripts malveillants.

**Alternative implémentée :**
- Backend : `htmlspecialchars($content, ENT_QUOTES, 'UTF-8')` pour échapper les caractères HTML
- Frontend : Suppression de `dangerouslySetInnerHTML` + fonction `decodeHtml()` pour affichage sécurisé
- React échappe automatiquement le contenu avec `{decodeHtml(comment.content)}`

### 3. Pour le CORS, quels sont les risques concrets de laisser `'*'` en production ?

- **CSRF** : sites malveillants peuvent faire des requêtes à l'API avec les credentials de la victime
- **Vol de données** : extraction de données privées depuis un domaine tiers
- **Exploitation de sessions** : actions non autorisées au nom de l'utilisateur connecté
- **Violation Same-Origin Policy** : n'importe quel domaine peut interagir avec l'API

**Solution :** Whitelist uniquement les domaines de confiance (`localhost:3000`, `localhost:8000` en dev, domaine prod en production).

### 4. Faut-il corriger côté backend, frontend, ou les deux ?

**LES DEUX (défense en profondeur)**

**Backend :**
- CORS : restreindre les origines autorisées dans `config/cors.php`
- XSS : sanitiser avec `htmlspecialchars()` avant stockage en DB

**Frontend :**
- Supprimer `dangerouslySetInnerHTML`
- Utiliser `{decodeHtml(content)}` pour affichage sécurisé

**Pourquoi les deux ?** Si une couche échoue, l'autre protège quand même. L'API peut être utilisée par d'autres clients (mobile, CLI).
