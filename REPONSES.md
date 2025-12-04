# Réponses aux Questions

## 1. Comment identifier la cause exacte du problème ?
J'ai vérifié 3 éléments :
- **Migration** (`2024_01_01_000002_create_articles_table.php`) : Table en `latin1_general_ci` ❌
- **Config** (`config/database.php`) : Connexion MySQL en `utf8_general_ci` 
- **Requête SQL** : SQL brut vulnérable avec injection possible

**Cause identifiée** : `latin1_general_ci` ne supporte pas la recherche insensible aux accents.

## 2. Comment gérer la migration sans supprimer les données ?
**Solution choisie** : Normalisation côté PHP (pas de migration nécessaire)
- Fonction `removeAccents()` qui transforme "café" → "cafe"
- Compare les versions normalisées : `removeAccents($title)` vs `removeAccents($query)`
- Conserve les données originales intactes

## 3. Comment tester tous les cas ?
Tests implémentés dans le code :
- **Accents** : "cafe" trouve "café", "ete" trouve "été" ✅
- **Majuscules** : `strtolower()` appliqué avant comparaison ✅
- **Caractères spéciaux** : Support de œ, æ, ñ, ç ✅
- **Sécurité** : Eloquent ORM élimine les injections SQL ✅
