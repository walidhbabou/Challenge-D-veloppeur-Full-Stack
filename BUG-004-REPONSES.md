# [BUG-004] Les dates s'affichent en anglais et timezone US

## 🔍 Problème identifié

Les dates des articles s'affichent :
- ❌ En format américain : "12/25/2024" au lieu de "25/12/2024"
- ❌ En timezone PST (America/Los_Angeles) au lieu de CET (Europe/Paris)
- ❌ En anglais : "December 25" au lieu de "25 décembre"

**Cause racine** :
`config/app.php` contient :
- Ligne 72 : `'timezone' => 'America/Los_Angeles'`
- Ligne 84 : `'locale' => 'en'`

## ✅ Solution implémentée

### Modification de `config/app.php`

```php
// Avant
'timezone' => 'America/Los_Angeles',
'locale' => 'en',

// Après
'timezone' => 'Europe/Paris',
'locale' => 'fr',
```

**Impact** :
- Toutes les fonctions `now()`, `Carbon::now()`, `created_at`, `updated_at` utilisent Europe/Paris
- Les traductions Laravel (si disponibles) s'affichent en français
- Les dates stockées en base restent en UTC (standard)

## 🧪 Comment tester

### Test 1 : Vérifier la timezone
```bash
cd project/backend
php artisan tinker

# Dans tinker :
echo config('app.timezone');
// Résultat attendu : Europe/Paris

echo now();
// Résultat attendu : 2024-12-05 15:30:00 (heure française)
```

### Test 2 : Vérifier la locale
```bash
php artisan tinker

# Dans tinker :
echo config('app.locale');
// Résultat attendu : fr
```

### Test 3 : Affichage des dates dans l'application
1. Ouvrir http://localhost:3000
2. Voir les dates des articles
3. **Résultat attendu** : Dates au format français avec timezone CET

### Test 4 : Créer un nouvel article
```bash
# L'heure doit correspondre à l'heure locale française
curl -X POST http://localhost:8000/api/articles \
  -H "Content-Type: application/json" \
  -d '{"title":"Test Date","content":"Contenu","author_id":1}'
  
# Vérifier created_at dans la réponse
```

## 📋 Réponses aux questions

### Q1 : Où se configure la timezone et la locale dans Laravel ?

**Localisation** : `config/app.php`

**Deux paramètres principaux** :
```php
'timezone' => 'Europe/Paris',  // Ligne 72
'locale' => 'fr',               // Ligne 84
```

**Autres fichiers potentiellement concernés** :
- `.env` : Peut surcharger avec `APP_TIMEZONE` et `APP_LOCALE` (mais pas défini ici)
- `config/database.php` : Timezone pour la connexion DB (optionnel)
- Frontend : Peut avoir sa propre config de formatage

**Hiérarchie de configuration** :
1. `.env` (prioritaire si défini)
2. `config/app.php` (valeur par défaut)

---

### Q2 : Faut-il modifier le backend, le frontend, ou les deux ?

**Backend (Laravel) : ✅ MODIFIÉ**
- `config/app.php` → timezone et locale changées
- Affecte toutes les dates générées par l'API
- Impact : `created_at`, `updated_at`, `now()`, etc.

**Frontend (React) : Dépend de l'implémentation**
- Si le frontend affiche les dates brutes de l'API → ✅ Déjà corrigé
- Si le frontend formate les dates côté client → ⚠️ Vérifier la locale JavaScript

**Vérification frontend** :
```javascript
// Dans App.jsx ou composants
new Date().toLocaleDateString('fr-FR')
// Format français : 05/12/2024

new Intl.DateTimeFormat('fr-FR', {
  dateStyle: 'long',
  timeZone: 'Europe/Paris'
}).format(new Date())
// Format : 5 décembre 2024
```

**Recommandation** : Backend corrigé suffit si l'API renvoie les dates formatées. Si le frontend utilise `new Date()`, ajouter la locale `fr-FR`.

---

### Q3 : Comment s'assurer que les dates stockées en base restent cohérentes ?

**Principe fondamental** : Laravel stocke TOUJOURS en UTC dans la base de données, peu importe la timezone de l'application.

**Vérification** :
```sql
-- Les dates en base sont en UTC
SELECT id, title, created_at FROM articles;
-- Exemple : 2024-12-05 14:30:00 (UTC)
```

**Conversion automatique par Laravel** :
```php
// En base : 2024-12-05 14:30:00 UTC
$article->created_at;
// Affiché : 2024-12-05 15:30:00 (converti en Europe/Paris, +1h)
```

**Pourquoi c'est important ?**
- ✅ Cohérence globale : Tous les serveurs stockent en UTC
- ✅ Pas de problème lors du changement de timezone
- ✅ Support multi-timezone : Chaque utilisateur peut avoir sa timezone

**Test de cohérence** :
```bash
php artisan tinker

# Créer un article
$article = Article::create([
    'title' => 'Test',
    'content' => 'Test',
    'author_id' => 1,
    'published_at' => now()
]);

# Voir la date affichée (Europe/Paris)
echo $article->published_at;
// 2024-12-05 15:30:00

# Voir la date brute en base (UTC)
echo $article->getAttributes()['published_at'];
// 2024-12-05 14:30:00
```

**Conclusion** : ✅ Aucun risque d'incohérence car Laravel gère automatiquement la conversion UTC ↔ Timezone locale.
