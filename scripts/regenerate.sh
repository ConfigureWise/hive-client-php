#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
SPEC_FILE="$PROJECT_DIR/OpenAPI Specification.json"
OUTPUT_DIR="$PROJECT_DIR/src/Generated"

if [[ "${1:-}" == "--clean" ]]; then
    echo "Cleaning generated code..."
    rm -rf "$OUTPUT_DIR"
fi

echo "Generating PHP client from OpenAPI specification..."
openapi-generator generate \
    -i "$SPEC_FILE" \
    -g php \
    -o "$PROJECT_DIR/.openapi-gen-tmp" \
    --additional-properties=invokerPackage='HiveCpq\Client\Generated' \
    --additional-properties=apiPackage=Api \
    --additional-properties=modelPackage=Model \
    --global-property=apiTests=false,modelTests=false,apiDocs=false,modelDocs=false

echo "Moving generated source files..."
rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR"
cp -r "$PROJECT_DIR/.openapi-gen-tmp/lib/"* "$OUTPUT_DIR/"

rm -rf "$PROJECT_DIR/.openapi-gen-tmp"

echo "Done. Generated code is in src/Generated/"
