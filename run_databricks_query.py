import sys
import json
from databricks import sql
import datetime
import os
from decimal import Decimal

class DateTimeEncoder(json.JSONEncoder):
    """Niestandardowy enkoder JSON do obsługi daty, czasu i typu Decimal."""
    def default(self, obj):
        if isinstance(obj, Decimal):
            return str(obj)
        if isinstance(obj, (datetime.datetime, datetime.date)):
            return obj.isoformat()
        return super(DateTimeEncoder, self).default(obj)

def main():
    """
    Uniwersalny skrypt do wykonywania zapytań SQL w Databricks.
    Oczekuje jednego argumentu: zapytania SQL do wykonania.
    """
    # --- DANE POŁĄCZENIOWE ---
    SERVER_HOSTNAME = "adb-5375732459006317.17.azuredatabricks.net"
    HTTP_PATH       = "/sql/1.0/warehouses/8899813ae5b1cb7d"
    ACCESS_TOKEN    = "dapi94f291f45a4109e638364e8ba8457012-2"

    # Sprawdź, czy zapytanie zostało przekazane jako argument
    if len(sys.argv) < 2:
        print(json.dumps({
            "status": "error",
            "message": "Błąd skryptu: Nie przekazano ścieżki do pliku z zapytaniem."
        }, cls=DateTimeEncoder))
        sys.exit(1)

    query_file_path = sys.argv[1]

    try:
        # Odczytaj zapytanie SQL z pliku tymczasowego
        with open(query_file_path, 'r', encoding='utf-8') as f:
            query = f.read()
    except Exception as e:
        print(json.dumps({"status": "error", "message": f"Błąd odczytu pliku z zapytaniem: {str(e)}"}, cls=DateTimeEncoder))
        sys.exit(1)

    try:
        with sql.connect(
            server_hostname=SERVER_HOSTNAME,
            http_path=HTTP_PATH,
            access_token=ACCESS_TOKEN
        ) as connection:
            with connection.cursor() as cursor:
                cursor.execute(query)
                
                column_names = [desc[0] for desc in cursor.description]
                results = [dict(zip(column_names, row)) for row in cursor.fetchall()]
                
                print(json.dumps({
                    "status": "success",
                    "data": results
                }, cls=DateTimeEncoder))

    except Exception as e:
        print(json.dumps({
            "status": "error",
            "message": f"Błąd Databricks: {str(e)}"
        }, cls=DateTimeEncoder))
        # Upewnij się, że plik tymczasowy jest usuwany nawet w przypadku błędu
        if os.path.exists(query_file_path):
            os.remove(query_file_path)
        sys.exit(1)
    finally:
        # Zawsze usuwaj plik tymczasowy po zakończeniu pracy
        if os.path.exists(query_file_path):
            os.remove(query_file_path)

if __name__ == "__main__":
    main()