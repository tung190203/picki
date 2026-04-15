#!/bin/bash
set -e

# ==============================
# MÀU SẮC & BIỂU TƯỢNG
# ==============================
GREEN="\033[0;32m"
RED="\033[0;31m"
YELLOW="\033[0;33m"
BLUE="\033[0;34m"
GRAY="\033[0;90m"
RESET="\033[0m"

CHECK="✔"
CROSS="✖"
ARROW="➜"
DOT="•"

step () { echo -e "\n${BLUE}${ARROW} $1${RESET}"; }
info () { echo -e "${GRAY}${DOT} $1${RESET}"; }
success () { echo -e "${GREEN}${CHECK} $1${RESET}"; }
error () { echo -e "${RED}${CROSS} $1${RESET}"; }

header () {
  echo -e "\n${GREEN}=============================="
  echo -e "🚀  BẮT ĐẦU TRIỂN KHAI LOCAL"
  echo -e "==============================${RESET}\n"
}

footer () {
  echo -e "\n${GREEN}=============================="
  echo -e "✅  TRIỂN KHAI HOÀN TẤT AN TOÀN"
  echo -e "==============================${RESET}\n"
}

# ==============================
# CONFIRM TRƯỚC KHI CHẠY
# ==============================
confirm_start () {
  echo -e "${YELLOW}Bạn có chắc muốn chạy deploy? (y/N)${RESET}"
  read -r CONFIRM

  if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
    echo -e "${RED}Đã huỷ deploy${RESET}"
    exit 0
  fi
}

# ==============================
# ROLLBACK
# ==============================
ORIGINAL_BRANCH=$(git branch --show-current)

rollback () {
  echo -e "\n${YELLOW}⚠ Đang rollback...${RESET}"

  git checkout "$ORIGINAL_BRANCH" || true
  git reset --hard || true
  git clean -fd || true

  echo -e "${GREEN}✔ Rollback hoàn tất${RESET}\n"
}

# ==============================
# BẮT LỖI
# ==============================
CURRENT_STEP=""
trap 'on_error $LINENO' ERR

on_error () {
  echo -e "\n${RED}==============================${RESET}"
  echo -e "${RED}${CROSS}  TRIỂN KHAI THẤT BẠI${RESET}"
  echo -e "${YELLOW}• Bước:${RESET} ${CURRENT_STEP}"
  echo -e "${GRAY}• Dòng:${RESET} $1"
  echo -e "${GRAY}• Đang tiến hành rollback...${RESET}"
  echo -e "${RED}==============================${RESET}\n"

  rollback
  exit 1
}

# ==============================
# BẮT ĐẦU
# ==============================
header
confirm_start

# ==============================
# KIỂM TRA AN TOÀN
# ==============================
step "Kiểm tra an toàn"

CURRENT_STEP="Kiểm tra branch hiện tại"
CURRENT_BRANCH=$(git branch --show-current)

if [ "$CURRENT_BRANCH" != "main" ]; then
  error "Script phải chạy từ MAIN"
  info "Hiện tại: $CURRENT_BRANCH"
  exit 1
fi

CURRENT_STEP="Kiểm tra public/build"
if [ -d "public/build" ]; then
  error "public/build tồn tại trên MAIN"
  info "Hãy xoá trước khi deploy"
  exit 1
fi

success "Kiểm tra OK"

# ==============================
# 1. UPDATE MAIN
# ==============================
step "Cập nhật MAIN"

CURRENT_STEP="Checkout main"
git checkout main

CURRENT_STEP="Pull main"
git pull origin main

success "Main đã cập nhật"

# ==============================
# 2. BUILD
# ==============================
BUILDS=(
  "dev|build:dev|build(dev): cập nhật assets|DEV"
  "deploy|build:prod|build(prod): cập nhật assets|PROD"
)

for BUILD in "${BUILDS[@]}"; do
  IFS="|" read -r BRANCH BUILD_CMD COMMIT_MSG LABEL <<< "$BUILD"

  step "Build $LABEL"

  CURRENT_STEP="Checkout $BRANCH"
  git checkout "$BRANCH"

  CURRENT_STEP="Merge main"
  git merge main --no-edit

  CURRENT_STEP="Xoá build cũ"
  rm -rf public/build

  CURRENT_STEP="Build"
  npm run "$BUILD_CMD"

  CURRENT_STEP="Commit"
  git add public/build
  git commit -m "$COMMIT_MSG" || info "Không có thay đổi"

  CURRENT_STEP="Push"
  git push origin "$BRANCH"

  success "$LABEL OK"
done

# ==============================
# 3. QUAY LẠI MAIN
# ==============================
step "Khôi phục MAIN"

CURRENT_STEP="Checkout main"
git checkout main

CURRENT_STEP="Clean"
git restore .
git clean -fd

CURRENT_STEP="Composer install"
composer install

footer