# [BUG-003] Upload d'image > 2MB - Documentation

## 🔍 Problème identifié

Le système refusait les images > 2MB avec une erreur **413 Payload Too Large**.

**Cause racine** :
1. **Dockerfile** (lignes 19-20) : Limites PHP configurées à `upload_max_filesize = 2M` et `post_max_size = 2M`
2. **ImageUploadController.php** : Validation Laravel `max:20480` (20MB) mais inutile car PHP bloquait avant
3. Incohérence entre validation Laravel (20MB) et configuration PHP (2MB)

## ✅ Solution implémentée

### 1. Configuration PHP (Dockerfile)
```dockerfile
# Avant
RUN echo "upload_max_filesize = 2M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 2M" >> /usr/local/etc/php/conf.d/uploads.ini

# Après (10MB)
RUN echo "upload_max_filesize = 10M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/uploads.ini
```

### 2. Validation Laravel (ImageUploadController.php)
```php
// Avant : max:20480 (20MB) - incohérent avec PHP
'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:20480'

// Après : max:10240 (10MB) - cohérent avec PHP
'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240'
```

### 3. Interface utilisateur (ImageUpload.jsx)
- Message d'erreur mis à jour : "limite 10MB" au lieu de "2MB"
- Instructions de test actualisées

## 🧪 Comment tester

### Prérequis
**IMPORTANT** : Rebuilder le container Docker car le Dockerfile a changé :
```bash
cd project
docker-compose down
docker-compose build --no-cache backend
docker-compose up -d
```

### Tests à effectuer

#### ✅ Test 1 : Image < 10MB (doit fonctionner)
1. Aller sur l'interface d'upload
2. Sélectionner une image de 5MB
3. Cliquer sur "Uploader"
4. **Résultat attendu** : ✅ "Image uploadée avec succès !"

#### ✅ Test 2 : Image > 10MB (doit échouer proprement)
1. Sélectionner une image de 12MB
2. Cliquer sur "Uploader"
3. **Résultat attendu** : ❌ "Erreur 413 : Image trop volumineuse ! La limite est de 10MB."

#### ✅ Test 3 : Validation côté Laravel (> 10MB)
1. Tester avec une image de 15MB
2. **Résultat attendu** : Erreur de validation Laravel avant l'upload

### Générer des images de test
```bash
# Linux/Mac - Créer une image de 5MB
dd if=/dev/zero of=test-5mb.jpg bs=1M count=5

# Linux/Mac - Créer une image de 12MB
dd if=/dev/zero of=test-12mb.jpg bs=1M count=12

# Windows PowerShell - Créer une image de 5MB
fsutil file createnew test-5mb.jpg 5242880

# Windows PowerShell - Créer une image de 12MB
fsutil file createnew test-12mb.jpg 12582912
```

## 📋 Réponses aux questions

### Q1 : Où se trouve la limite d'upload ? Comment l'identifier ?

**4 niveaux de limites possibles** :

#### 1. PHP (`upload_max_filesize` et `post_max_size`)
```bash
# Vérifier depuis le container
docker exec blog_backend php -i | grep upload_max_filesize
docker exec blog_backend php -i | grep post_max_size
```
**Localisation** : Dockerfile ligne 19-20 → `/usr/local/etc/php/conf.d/uploads.ini`
**Impact** : Bloque AVANT que Laravel ne traite la requête → Erreur 413

#### 2. Laravel (validation `max:`)
```php
// ImageUploadController.php ligne 14
'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240'
```
**Impact** : Validation applicative APRÈS réception par PHP

#### 3. Apache (`LimitRequestBody`)
```bash
# Vérifier la config Apache
docker exec blog_backend cat /etc/apache2/apache2.conf | grep LimitRequestBody
```
**État actuel** : Non configuré (pas de limite)

#### 4. Docker Compose (aucune limite par défaut)
**État actuel** : Pas de restriction au niveau Docker

**Ordre d'exécution** :
1. Docker → 2. Apache → 3. PHP → 4. Laravel

**Dans notre cas** : PHP était le bloqueur (2MB) avant que Laravel (20MB) ne puisse valider.

---

### Q2 : Comment modifier dans Docker sans tout reconstruire ?

**Méthode 1 : Rebuild complet (RECOMMANDÉE)**
```bash
cd project
docker-compose down
docker-compose build --no-cache backend
docker-compose up -d
```
✅ Garantit que les changements du Dockerfile sont appliqués  
✅ Pas de perte de données (volumes séparés)  
⏱️ Prend 2-3 minutes

**Méthode 2 : Modifier à chaud (temporaire, pour tests)**
```bash
# Modifier directement dans le container
docker exec -it blog_backend bash
echo "upload_max_filesize = 10M" > /usr/local/etc/php/conf.d/uploads.ini
echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/uploads.ini
apache2ctl graceful  # Recharger Apache
exit
```
⚠️ **Attention** : Modifications perdues au prochain redémarrage du container

**Méthode 3 : Volume mount (pour développement)**
Ajouter dans `docker-compose.yml` :
```yaml
volumes:
  - ./php-custom.ini:/usr/local/etc/php/conf.d/custom.ini
```
✅ Modifications persistantes  
✅ Pas de rebuild nécessaire  
❌ Plus complexe à maintenir

**Solution choisie** : Méthode 1 (Rebuild) car :
- Changements définitifs
- Reproductible en production
- Dockerfile = source de vérité

---

### Q3 : Comment vérifier que la modification a bien été appliquée ?

#### ✅ Vérification 1 : Configuration PHP
```bash
# Vérifier upload_max_filesize
docker exec blog_backend php -r "echo ini_get('upload_max_filesize');"
# Attendu : 10M

# Vérifier post_max_size
docker exec blog_backend php -r "echo ini_get('post_max_size');"
# Attendu : 10M

# Voir toutes les valeurs upload
docker exec blog_backend php -i | grep -E "upload|post_max"
```

#### ✅ Vérification 2 : Test fonctionnel
```bash
# Créer un fichier de test de 5MB
fsutil file createnew test-5mb.jpg 5242880

# Tester l'upload via curl
curl -X POST http://localhost:8000/api/articles/upload \
  -F "image=@test-5mb.jpg" \
  -H "Content-Type: multipart/form-data"
```
**Résultat attendu** : `{"message":"Image uploaded successfully",...}`

#### ✅ Vérification 3 : Logs Apache/PHP
```bash
# Voir les logs en temps réel
docker logs -f blog_backend

# Chercher les erreurs upload
docker exec blog_backend tail -n 50 /var/log/apache2/error.log
```

#### ✅ Vérification 4 : Interface graphique
1. Ouvrir http://localhost:3000
2. Aller sur la page d'upload
3. Sélectionner une image de 5MB
4. Vérifier le message de succès

**Checklist complète** :
- [ ] `php -i` affiche 10M pour upload_max_filesize ✅
- [ ] `php -i` affiche 10M pour post_max_size ✅
- [ ] Upload de 5MB réussit via interface ✅
- [ ] Upload de 12MB échoue proprement (erreur 413) ✅
- [ ] Logs Apache ne montrent pas d'erreurs ✅
