# Navigate to your project's root directory if you're not already there
# cd /path/to/your/project

# --- 1. Update CSS file paths in PHP/HTML files (e.g., index.php, display_table.php) ---
echo "--- Previewing CSS path changes ---"
# Using a simpler while loop with read, assuming filenames don't contain newlines
# For absolute robustness with filenames containing newlines, you'd need a more complex approach,
# but for typical web project filenames, this is usually fine.
find . -maxdepth 1 -type f \( -name "index.php" -o -name "display_table.php" \) | while IFS= read -r file; do
  echo "Changes in: $file"
  sed -nE 's|href="([^/]+\.css)"|href="css/\1"|p' "$file"
  echo ""
done

printf "Apply CSS path changes? (y/N) "
read -r REPLY
echo # Newline after response
if [[ "$REPLY" =~ ^[Yy]$ ]]; then
  echo "--- Applying CSS path changes ---"
  find . -maxdepth 1 -type f \( -name "index.php" -o -name "display_table.php" \) | while IFS= read -r file; do
    echo "Updating: $file"
    if [[ "$(uname)" == "Darwin" ]]; then # macOS specific sed syntax
      sed -i '' -E 's|href="([^/]+\.css)"|href="css/\1"|g' "$file"
    else # Linux sed syntax
      sed -i -E 's|href="([^/]+\.css)"|href="css/\1"|g' "$file"
    fi
  done
  echo "CSS paths updated."
else
  echo "CSS path changes aborted."
fi

# --- 2. Update JavaScript file paths (non-oneko) in PHP/HTML files ---
echo "--- Previewing non-oneko JS path changes ---"
find . -maxdepth 1 -type f \( -name "index.php" -o -name "display_table.php" \) | while IFS= read -r file; do
  echo "Changes in: $file"
  sed -nE 's|src="([^/]+(\.js))"|src="js/\1"|p' "$file"
  echo ""
done

printf "Apply non-oneko JS path changes? (y/N) "
read -r REPLY
echo
if [[ "$REPLY" =~ ^[Yy]$ ]]; then
  echo "--- Applying non-oneko JS path changes ---"
  find . -maxdepth 1 -type f \( -name "index.php" -o -name "display_table.php" \) | while IFS= read -r file; do
    echo "Updating: $file"
    if [[ "$(uname)" == "Darwin" ]]; then
      sed -i '' -E 's|src="([^/]+(\.js))"|src="js/\1"|g' "$file"
    else
      sed -i -E 's|src="([^/]+(\.js))"|src="js/\1"|g' "$file"
    fi
  done
  echo "Non-oneko JS paths updated."
else
  echo "Non-oneko JS path changes aborted."
fi

# --- 3. Update oneko.js and oneko.gif paths in PHP/HTML files ---
echo "--- Previewing oneko paths changes ---"
find . -maxdepth 1 -type f \( -name "index.php" -o -name "display_table.php" \) | while IFS= read -r file; do
  echo "Changes in: $file"
  sed -nE 's|src="(oneko\.js)"|src="js/oneko/\1"|p' "$file"
  sed -nE 's|src="(oneko\.gif)"|src="js/oneko/\1"|p' "$file"
  echo ""
done

printf "Apply oneko paths changes? (y/N) "
read -r REPLY
echo
if [[ "$REPLY" =~ ^[Yy]$ ]]; then
  echo "--- Applying oneko paths changes ---"
  find . -maxdepth 1 -type f \( -name "index.php" -o -name "display_table.php" \) | while IFS= read -r file; do
    echo "Updating: $file"
    if [[ "$(uname)" == "Darwin" ]]; then
      sed -i '' -E 's|src="(oneko\.js)"|src="js/oneko/\1"|g' "$file"
      sed -i '' -E 's|src="(oneko\.gif)"|src="js/oneko/\1"|g' "$file"
    else
      sed -i -E 's|src="(oneko\.js)"|src="js/oneko/\1"|g' "$file"
    fi
  done
  echo "Oneko paths updated."
else
  echo "Oneko paths changes aborted."
fi

# --- 4. Update PHP include paths (specifically for db_config.php) ---
echo "--- Previewing PHP include path changes ---"
find . -maxdepth 1 -type f \( -name "index.php" -o -name "display_table.php" \) | while IFS= read -r file; do
  echo "Changes in: $file"
  # This pattern looks for 'require_once', 'include_once', 'require', 'include'
  sed -nE 's|(require_once|include_once|require|include)[[:space:]]*["'\'']db_config\.php["'\'']|\1 '\''include/db_config.php'\''|p' "$file"
  echo ""
done

printf "Apply PHP include path changes? (y/N) "
read -r REPLY
echo
if [[ "$REPLY" =~ ^[Yy]$ ]]; then
  echo "--- Applying PHP include path changes ---"
  find . -maxdepth 1 -type f \( -name "index.php" -o -name "display_table.php" \) | while IFS= read -r file; do
    echo "Updating: $file"
    if [[ "$(uname)" == "Darwin" ]]; then
      sed -i '' -E 's|(require_once|include_once|require|include)[[:space:]]*["'\'']db_config\.php["'\'']|\1 '\''include/db_config.php'\''|g' "$file"
    else
      sed -i -E 's|(require_once|include_once|require|include)[[:space:]]*["'\'']db_config\.php["'\'']|\1 '\''include/db_config.php'\''|g' "$file"
    fi
  done
  echo "PHP include paths updated."
else
  echo "PHP include path changes aborted."
fi
