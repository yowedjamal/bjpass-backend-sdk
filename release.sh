#!/bin/bash
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

check_requirements() {
  local missing=0
  for cmd in git-flow npm composer; do
    if ! command -v $cmd &> /dev/null; then
      echo -e "${RED}✗ $cmd non installé${NC}"
      missing=1
    fi
  done
  [ $missing -eq 0 ] || exit 1
}

# Validation version
validate_version() {
  if ! [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo -e "${RED}❌ Format de version invalide (X.Y.Z)${NC}"
    exit 1
  fi
}

check_requirements

if [ "$#" -ne 1 ]; then
  echo -e "${RED}❌ Usage: $0 <version>${NC}"
  echo "Exemple: $0 1.0.0"
  exit 1
fi

VERSION=$1
validate_version $VERSION

CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" != "develop" ]; then
  echo -e "${RED}❌ Branche actuelle: $CURRENT_BRANCH (devrait être develop)${NC}"
  exit 1
fi

# 1. Démarrage release
echo -e "${GREEN}🚀 Démarrage release $VERSION...${NC}"
git flow release start $VERSION

# 2. MAJ version MANUELLE (au lieu de npm version)
echo -e "${GREEN}🔄 Mise à jour version composer.json...${NC}"
perl -pi -e "s/\"version\": \".*?\"/\"version\": \"$VERSION\"/" composer.json
git add composer.json
git commit -m "chore(release): v$VERSION [skip ci]"

# 3. Finalisation
echo -e "${GREEN}🏁 Finalisation release...${NC}"
git flow release finish -m "$VERSION" $VERSION --keepremote

# 4. Push
echo -e "${GREEN}📡 Push vers GitHub...${NC}"
git push origin develop master --tags

# 5. GitHub Release
if command -v gh &> /dev/null; then
  echo -e "${GREEN}📦 Création release GitHub...${NC}"
  gh release create $VERSION --generate-notes
else
  echo -e "ℹ️  Installez GitHub CLI (gh) pour créer automatiquement les releases"
fi

echo -e "\n${GREEN}✅ Release $VERSION complétée!${NC}"