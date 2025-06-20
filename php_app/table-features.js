document.addEventListener('DOMContentLoaded', function() {
    const table = document.querySelector('.data-table');
    if (!table) return;

    // Add filter inputs
    const filterRow = document.createElement('tr');
    filterRow.className = 'filter-row';
    
    // Get all table headers
    const headers = Array.from(table.querySelectorAll('th'));
    
    // Create filter inputs for each column
    headers.forEach((header, index) => {
        const td = document.createElement('td');
        const input = document.createElement('input');
        input.type = 'text';
        input.placeholder = `Filter ${header.textContent}`;
        input.className = 'filter-input';
        input.dataset.column = index;
        td.appendChild(input);
        filterRow.appendChild(td);
    });

    // Insert filter row after header
    const tbody = table.querySelector('tbody');
    tbody.insertBefore(filterRow, tbody.firstChild);

    // Sorting functionality
    let sortColumn = null;
    let sortDirection = 1; // 1 for ascending, -1 for descending

    headers.forEach((header, index) => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', () => {
            // Remove sort indicators from all headers
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            
            // Update sort direction
            if (sortColumn === index) {
                sortDirection = -sortDirection;
            } else {
                sortColumn = index;
                sortDirection = 1;
            }

            // Add sort indicator to current header
            header.classList.add(sortDirection === 1 ? 'sort-asc' : 'sort-desc');

            // Get all data rows (excluding filter row)
            const rows = Array.from(tbody.querySelectorAll('tr:not(.filter-row)'));
            
            // Sort rows
            rows.sort((a, b) => {
                const aVal = a.cells[index].textContent.trim();
                const bVal = b.cells[index].textContent.trim();
                
                // Check if the values are numbers
                const aNum = parseFloat(aVal);
                const bNum = parseFloat(bVal);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return (aNum - bNum) * sortDirection;
                }
                
                return aVal.localeCompare(bVal) * sortDirection;
            });

            // Reorder rows in the table
            rows.forEach(row => tbody.appendChild(row));
        });
    });

    // Filtering functionality
    let filterTimeouts = {};
    
    document.querySelectorAll('.filter-input').forEach(input => {
        input.addEventListener('input', function() {
            const column = this.dataset.column;
            
            // Clear existing timeout for this column
            if (filterTimeouts[column]) {
                clearTimeout(filterTimeouts[column]);
            }
            
            // Set new timeout to prevent too many filter operations
            filterTimeouts[column] = setTimeout(() => {
                const filterValues = {};
                
                // Collect all filter values
                document.querySelectorAll('.filter-input').forEach(input => {
                    filterValues[input.dataset.column] = input.value.toLowerCase();
                });
                
                // Filter rows
                const rows = tbody.querySelectorAll('tr:not(.filter-row)');
                rows.forEach(row => {
                    let show = true;
                    
                    // Check each filter
                    Object.entries(filterValues).forEach(([column, value]) => {
                        const cellText = row.cells[column].textContent.toLowerCase();
                        if (value && !cellText.includes(value)) {
                            show = false;
                        }
                    });
                    
                    row.style.display = show ? '' : 'none';
                });
            }, 300); // Delay of 300ms
        });
    });
});
