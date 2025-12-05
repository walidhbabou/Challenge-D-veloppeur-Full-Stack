# [SEC-001] Les mots de passe sont stockés en clair - CRITIQUE 🔴

## 🔍 Problème identifié

⚠️ **FAILLE DE SÉCURITÉ MAJEURE** ⚠️

Les mots de passe des utilisateurs étaient stockés **en clair** (plain text) dans la table `users`.

**Fichiers affectés** :
1. `DatabaseSeeder.php` : Insertait les mots de passe en clair
2. `AuthController.php` (ligne 27) : Comparaison directe sans vérifier le hash
3. `AuthController.php` (ligne 54) : Enregistrement sans hasher
4. `User.php` : Manquait le cast `'password' => 'hashed'`

**Risques** :
- ✅ Si la DB est compromise → tous les mots de passe exposés
- ✅ Violation RGPD
- ✅ Violation standards OWASP

## ✅ Solution implémentée

### 1. DatabaseSeeder.php
```php
// AVANT
use Illuminate\Support\Facades\DB;
$users = [
    ['email' => 'admin@blog.com', 'password' => 'Admin123!'],
];

// APRÈS
use Illuminate\Support\Facades\Hash;
$users = [
    ['email' => 'admin@blog.com', 'password' => Hash::make('Admin123!')],
];
```

### 2. AuthController.php - Login
```php
// AVANT (ligne 27)
if ($user->password !== $credentials['password']) {
    return response()->json(['message' => 'Invalid credentials'], 401);
}

// APRÈS
if (!Hash::check($credentials['password'], $user->password)) {
    return response()->json(['message' => 'Invalid credentials'], 401);
}
```

### 3. AuthController.php - Register
```php
// AVANT (ligne 54)
$user = User::create([
    'password' => $validated['password'],
]);

// APRÈS
$user = User::create([
    'password' => Hash::make($validated['password']),
]);
```

### 4. User.php - Model
```php
// AJOUT du cast automatique
protected $casts = [
    'email_verified_at' => 'datetime',
    'password' => 'hashed', // Laravel 10+ hashe automatiquement
];
```

## 🧪 Comment tester

### Prérequis : Re-seeder la base avec les mots de passe hashés
```bash
cd project/backend

# Réinitialiser et re-seeder
php artisan migrate:fresh --seed
```

### Test 1 : Vérifier que les mots de passe sont hashés
```bash
php artisan tinker

# Dans tinker :
User::all()->pluck('email', 'password');

# Résultat attendu :
# "$2y$10$..." => "admin@blog.com"
# Les mots de passe doivent commencer par $2y$10$ (bcrypt)
```

### Test 2 : Vérifier via SQL direct
```bash
# Option 1 : Via tinker
php artisan tinker
DB::select('SELECT email, password FROM users');

# Option 2 : Via Docker MySQL CLI
docker exec -it blog_mysql mysql -u blog_user -pblog_password blog_db
SELECT email, SUBSTRING(password, 1, 20) FROM users;

# Résultat attendu :
# admin@blog.com | $2y$10$92IXU...
# Les 7 premiers caractères doivent être "$2y$10$"
```

### Test 3 : Tester le login avec mot de passe haché
```bash
# Test de login (doit fonctionner)
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@blog.com",
    "password": "Admin123!"
  }'

# Résultat attendu : {"message":"Login successful", "user":{...}}
```

### Test 4 : Tester le register
```bash
# Créer un nouvel utilisateur
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "SecurePass123!"
  }'

# Vérifier que le mot de passe est hashé
php artisan tinker
User::where('email', 'test@example.com')->first()->password;
// Doit afficher : "$2y$10$..."
```

### Test 5 : Vérifier qu'on ne peut plus se connecter avec le hash
```bash
# Essayer de se connecter avec le hash (doit échouer)
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@blog.com",
    "password": "$2y$10$92IXU..."
  }'

# Résultat attendu : {"message":"Invalid credentials"}
# Prouve que le mot de passe original est bien irréversible
```

## 📋 Réponses aux questions

### Q1 : Qu'as-tu utilisé pour te connecter à la DB et vérifier les mots de passe ?

**3 outils possibles** :

#### 1. Artisan Tinker (Recommandé)
```bash
php artisan tinker
DB::select('SELECT email, password FROM users');
```
✅ Rapide, intégré à Laravel  
✅ Pas besoin de client externe

#### 2. Docker MySQL CLI
```bash
docker exec -it blog_mysql mysql -u blog_user -pblog_password blog_db
SELECT email, password FROM users;
```
✅ Accès direct à la base  
✅ Utile pour vérifications rapides

#### 3. GUI (phpMyAdmin, TablePlus, DBeaver)
```bash
# Si phpMyAdmin était installé dans docker-compose.yml
Host: localhost
Port: 3306
User: blog_user
Password: blog_password
Database: blog_db
```
✅ Interface visuelle  
❌ Nécessite installation supplémentaire

**Outil utilisé** : `php artisan tinker` (le plus pratique pour ce projet)

---

### Q2 : Comment migrer les mots de passe existants vers des mots de passe hashés ?

**Situation** : Les utilisateurs existent déjà en base avec mots de passe en clair.

**Solution 1 : Migration avec reset de mots de passe (IMPOSSIBLE ici)**
```php
// Créer une migration
php artisan make:migration hash_existing_passwords

// Ne fonctionne PAS car on ne peut pas récupérer les mots de passe en clair
// et on ne connaît pas les mots de passe originaux
```
❌ Impossible de hasher les mots de passe existants car on ne les connaît pas

**Solution 2 : Fresh migration (UTILISÉE)**
```bash
php artisan migrate:fresh --seed
```
✅ Recrée la DB avec les mots de passe hashés  
✅ Simple et propre pour développement  
⚠️ Perte des données (acceptable en dev)

**Solution 3 : Production (si utilisateurs réels)**
```php
// 1. Forcer reset de mot de passe pour tous
User::all()->each(function($user) {
    Password::sendResetLink(['email' => $user->email]);
});

// 2. Ou définir un mot de passe temporaire
User::all()->each(function($user) {
    $user->update(['password' => Hash::make('TempPassword123!')]);
    Mail::to($user->email)->send(new PasswordResetRequired());
});
```
✅ Sécurisé  
✅ Pas de perte de données utilisateurs  
⚠️ Nécessite communication aux utilisateurs

**Conclusion** : Dans ce projet, `migrate:fresh --seed` suffit car c'est un environnement de développement.

---

### Q3 : Comment s'assurer que l'authentification fonctionne toujours ?

**Tests à effectuer** :

#### 1. Test de login avec bon mot de passe
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@blog.com","password":"Admin123!"}'
```
**Attendu** : `{"message":"Login successful"}`  
**Vérifie** : `Hash::check()` fonctionne correctement

#### 2. Test de login avec mauvais mot de passe
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@blog.com","password":"WrongPassword"}'
```
**Attendu** : `{"message":"Invalid credentials"}`  
**Vérifie** : La vérification refuse les mauvais mots de passe

#### 3. Test d'enregistrement
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"New User","email":"new@test.com","password":"NewPass123!"}'
```
**Attendu** : `{"message":"User registered successfully"}`  
**Vérifie** : `Hash::make()` est appliqué automatiquement

#### 4. Vérification en base
```bash
php artisan tinker
$user = User::where('email', 'new@test.com')->first();
echo substr($user->password, 0, 7); // Doit afficher "$2y$10$"
```
**Vérifie** : Le mot de passe est bien hashé en base

**Checklist de sécurité** :
- [x] Mots de passe hashés en base (`$2y$10$...`)
- [x] Login fonctionne avec bon mot de passe
- [x] Login échoue avec mauvais mot de passe
- [x] Nouveaux utilisateurs ont mots de passe hashés
- [x] Impossible de retrouver mot de passe original
- [x] `Hash::check()` utilisé au lieu de comparaison directe

---

### Q4 : Où modifier le code pour que les futurs utilisateurs aient des mots de passe hashés ?

**3 niveaux de protection implémentés** :

#### 1. Model User (Protection automatique)
```php
// app/Models/User.php
protected $casts = [
    'password' => 'hashed',
];
```
✅ Laravel 10+ hashe automatiquement lors de `User::create()`  
✅ Protection au niveau le plus bas

#### 2. Controllers (Protection explicite)
```php
// app/Http/Controllers/AuthController.php
User::create([
    'password' => Hash::make($request->password),
]);
```
✅ Hash explicite même si le cast échoue  
✅ Double sécurité

#### 3. Seeders (Protection développement)
```php
// database/seeders/DatabaseSeeder.php
'password' => Hash::make('Admin123!'),
```
✅ Garantit que les données de test sont sécurisées

**Ordre de priorité** :
1. Cast dans Model → Appliqué automatiquement
2. Hash explicite → Si le cast ne fonctionne pas
3. Validation → S'assurer que le mot de passe respecte les critères

**Bonne pratique** : Toujours utiliser les deux (cast + Hash explicite) pour une sécurité maximale.

## 🔐 Standards de sécurité appliqués

✅ **OWASP** : Mots de passe hashés avec bcrypt  
✅ **RGPD** : Données sensibles protégées  
✅ **Laravel Best Practices** : Utilisation de Hash facade  
✅ **Bcrypt** : Algorithme résistant aux attaques par force brute
