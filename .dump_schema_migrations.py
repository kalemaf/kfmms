import sqlite3
conn = sqlite3.connect('database/maintenix.db')
for row in conn.execute("SELECT migration, applied_at FROM schema_migrations ORDER BY migration"):
    print(row)
conn.close()
