import sys
import json
from databricks import sql
import os
import datetime
from decimal import Decimal # Importujemy typ Decimal

def main():
    # Niestandardowy enkoder JSON, który potrafi konwertować obiekty daty i czasu na tekst.
    class DateTimeEncoder(json.JSONEncoder):
        def default(self, obj):
            # Jeśli obiekt jest typu Decimal, zamień go na string
            if isinstance(obj, Decimal):
                return str(obj)
            # Jeśli obiekt jest typu data lub data+czas, zamień go na string w formacie ISO
            if isinstance(obj, (datetime.datetime, datetime.date)):
                return obj.isoformat()
            # Dla wszystkich innych typów użyj domyślnego enkodera
            return super(DateTimeEncoder, self).default(obj)


    """
    Główna funkcja skryptu: łączy się z Databricks, wykonuje zapytanie
    i zwraca wynik jako JSON.
    """
    # --- DANE POŁĄCZENIOWE (takie same jak wcześniej) ---
    SERVER_HOSTNAME = "adb-5375732459006317.17.azuredatabricks.net"
    HTTP_PATH       = "/sql/1.0/warehouses/8899813ae5b1cb7d"
    # Token wpisany na stałe w kodzie dla wygody
    ACCESS_TOKEN = "dapi94f291f45a4109e638364e8ba8457012-2"

    # --- ZAPYTANIE SQL ---
    # Zapytanie do analizy "martwego magazynu" (Dead Stock)
    # WAŻNE: To jest szablon. Będziesz musiał zastąpić 'product_id_column', 'quantity_column',
    # 'movement_date', 'shipment_date_column', 'order_date_column', 'product_name_column',
    # 'stock_value_column' rzeczywistymi nazwami kolumn z Twoich tabel.
    QUERY = """
    -- KROK DIAGNOSTYCZNY: Pobieramy wszystkie kolumny z tabeli, do której mamy dostęp,
    -- aby znaleźć właściwe nazwy dla ID produktu i ilości.
    SELECT * FROM rambase.bronze.rb_inventory_confidential LIMIT 10;
    """

    try:
        with sql.connect(
            server_hostname=SERVER_HOSTNAME,
            http_path=HTTP_PATH,
            access_token=ACCESS_TOKEN
        ) as connection:
            with connection.cursor() as cursor:
                cursor.execute(QUERY)
                
                # Pobierz nazwy kolumn
                column_names = [desc[0] for desc in cursor.description]
                
                # Pobierz wszystkie wiersze i przekształć je w listę słowników
                results = [dict(zip(column_names, row)) for row in cursor.fetchall()]
                
                # Zwróć wynik jako JSON
                print(json.dumps({
                    "status": "success",
                    "data": results
                }, cls=DateTimeEncoder))

    except Exception as e:
        # W przypadku błędu, zwróć błąd w formacie JSON
        print(json.dumps({
            "status": "error",
            "message": f"Błąd podczas połączenia lub wykonywania zapytania: {str(e)}"
        }, cls=DateTimeEncoder))
        sys.exit(1)

if __name__ == "__main__":
    main()