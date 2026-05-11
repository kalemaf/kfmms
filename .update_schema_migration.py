import sqlite3
conn = sqlite3.connect('database/maintenix.db')
cur = conn.cursor()
cur.execute("UPDATE schema_migrations SET migration = ? WHERE migration = ?", ('000_initial_schema.sql', '001_initial_schema.sql'))
conn.commit()
cur.close()
conn.close()
print('Updated schema_migrations entry')
