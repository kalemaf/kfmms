import os, sqlite3
path = os.path.join(r'c:\free-cmms 0.04', 'database', 'maintenix.db')
with open(r'c:\free-cmms 0.04\warehouse_fk_debug.txt', 'w', encoding='utf-8') as out:
    out.write(f'DB: {path}\n')
    conn = sqlite3.connect(path)
    cur = conn.cursor()
    for table in ['warehouses', 'warehouse_locations', 'stock_locales', 'goods_receipts', 'goods_receipt_items', 'stock_transactions', 'inventory_transactions', 'purchase_orders', 'purchase_order_items']:
        out.write('\nTABLE: ' + table + '\n')
        try:
            for row in cur.execute("PRAGMA foreign_key_list('%s')" % table):
                out.write('FK: ' + str(row) + '\n')
        except Exception as e:
            out.write('ERROR: ' + str(e) + '\n')
    out.write('\nSCHEMA WAREHOUSES:\n')
    for row in cur.execute("SELECT sql FROM sqlite_master WHERE type='table' AND name='warehouses'"):
        out.write(str(row) + '\n')
    out.write('\nSCHEMA WAREHOUSE_LOCATIONS:\n')
    for row in cur.execute("SELECT sql FROM sqlite_master WHERE type='table' AND name='warehouse_locations'"):
        out.write(str(row) + '\n')
    out.write('\nSCHEMA STOCK_LOCALES:\n')
    for row in cur.execute("SELECT sql FROM sqlite_master WHERE type='table' AND name='stock_locales'"):
        out.write(str(row) + '\n')
    conn.close()
