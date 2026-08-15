#!/bin/bash
# Captura screenshots dos projetos para o portfólio
# Dependências: chromium-browser (ou google-chrome)
# https://www.chromium.org/getting-involved/download-chromium/

OUTPUT_DIR="/home/lz-ntn/portfolio-screenshots"
mkdir -p "$OUTPUT_DIR"

echo "📸 A capturar screenshots para $OUTPUT_DIR"
echo

# 1. Gestão BH - Dashboard
echo "1/4 · Gestão BH - Dashboard"
chromium-browser --headless --disable-gpu --window-size=1280,800 \
    --screenshot="$OUTPUT_DIR/gestaoBH-dashboard.png" \
    "http://localhost/gestaoBH/dashboard.php" 2>/dev/null

# 2. Gestão BH - Orçamentos
echo "2/4 · Gestão BH - Orçamentos"
chromium-browser --headless --disable-gpu --window-size=1280,800 \
    --screenshot="$OUTPUT_DIR/gestaoBH-orcamentos.png" \
    "http://localhost/gestaoBH/orcamento_pedido/" 2>/dev/null

# 3. PoupaPlus CRM - Dashboard (se estiver a correr)
echo "3/4 · CRM - Dashboard"
if curl -s -o /dev/null -w "%{http_code}" http://localhost:3000 2>/dev/null | grep -q 200; then
    chromium-browser --headless --disable-gpu --window-size=1280,800 \
        --screenshot="$OUTPUT_DIR/crm-dashboard.png" \
        "http://localhost:3000/#dashboard" 2>/dev/null
else
    echo "   ⚠️ Servidor CRM não está a correr em :3000. Salta captura."
fi

# 4. PoupaPlus CRM - Clientes
echo "4/4 · CRM - Clientes"
if curl -s -o /dev/null -w "%{http_code}" http://localhost:3000 2>/dev/null | grep -q 200; then
    chromium-browser --headless --disable-gpu --window-size=1280,800 \
        --screenshot="$OUTPUT_DIR/crm-clientes.png" \
        "http://localhost:3000/#clientes" 2>/dev/null
else
    echo "   ⚠️ Salta captura."
fi

echo
echo "✅ Capturas em: $OUTPUT_DIR"
echo "   Usa estas imagens no portfólio e no perfil Upwork!"
ls -lh "$OUTPUT_DIR"
