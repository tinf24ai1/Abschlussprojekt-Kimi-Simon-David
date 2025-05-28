import os
import sys
import mysql.connector # Requires mysql-connector-python

def main():
    db_host = os.environ.get('DB_HOST')
    db_user = os.environ.get('DB_USER')
    db_pass = os.environ.get('DB_PASS')
    db_name = os.environ.get('DB_NAME')

    if not all([db_host, db_user, db_pass, db_name]):
        print("Error: Database environment variables not set.")
        print("Required: DB_HOST, DB_USER, DB_PASS, DB_NAME")
        sys.exit(1)

    print("--- Interactive Table Creation/Update ---")
    table_name = input("Enter the name of the table: ").strip()
    if not table_name:
        print("Error: Table name cannot be empty.")
        sys.exit(1)

    columns_str = input("Enter column names, comma-separated (e.g., ID,Name,Value): ").strip()
    columns = [col.strip() for col in columns_str.split(',') if col.strip()]
    if not columns:
        print("Error: No columns provided.")
        sys.exit(1)

    table_data = []
    print(f"\nEnter row data for table '{table_name}' with columns: {', '.join(columns)}")
    print("Each row on a new line, values comma-separated.")
    print("Enter an empty line (just press Enter) when you are done adding rows.")
    
    row_num = 1
    while True:
        row_str = input(f"Row {row_num} (expected {len(columns)} values, comma-separated): ").strip()
        if not row_str:
            break
        
        values = [val.strip() for val in row_str.split(',')]
        if len(values) != len(columns):
            print(f"Error: Expected {len(columns)} values, but got {len(values)}. Please re-enter the row or an empty line to finish.")
            continue
        table_data.append(tuple(values))
        row_num += 1

    conn = None
    try:
        conn = mysql.connector.connect(
            host=db_host,
            user=db_user,
            password=db_pass,
            database=db_name
        )
        cursor = conn.cursor()

        cursor.execute(f"DROP TABLE IF EXISTS `{table_name}`")
        print(f"\nDropped table `{table_name}` if it existed.")

        column_definitions = [f"`{col.replace('`', '``')}` VARCHAR(255)" for col in columns]
        create_table_sql = f"CREATE TABLE `{table_name}` ({', '.join(column_definitions)})"
        cursor.execute(create_table_sql)
        print(f"Created table `{table_name}` with columns: {', '.join(columns)}")

        if table_data:
            column_names_sql = ', '.join([f"`{col.replace('`', '``')}`" for col in columns])
            placeholders = ', '.join(['%s'] * len(columns))
            insert_sql = f"INSERT INTO `{table_name}` ({column_names_sql}) VALUES ({placeholders})"
            cursor.executemany(insert_sql, table_data)
            print(f"Inserted {len(table_data)} rows into `{table_name}`.")
        else:
            print("No data rows provided to insert.")

        conn.commit()
        print(f"\nTable '{table_name}' in database '{db_name}' processed successfully.")

    except mysql.connector.Error as err:
        print(f"MySQL error: {err}")
        if conn and conn.is_connected():
            conn.rollback()
        sys.exit(1)
    except Exception as e:
        print(f"An unexpected error occurred: {e}")
        sys.exit(1)
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == '__main__':
    main()
