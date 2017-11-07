# Release Notes für PayPal

## 1.2.1 (2017-10-19)

### Gefixt

- Rückerstattung wird nun korrekt durchgeführt
- Zusätzliche Payment-Daten werden jetzt von PayPal übernommen und in plentymarkets abgespeichert.

## 1.2.0 (2017-10-11)

### Hinzugefügt

- PayPal Express zeigt eine Übersichtsseite vor dem Kaufabschluss

## 1.1.4 (2017-10-09)

### Gefixt

- PayPal Express wird als korrekte Zahlungsart gesetzt

## 1.1.3 (2017-10-04)

### Gefixt

- Zusätzliche Payment-Daten werden jetzt von PayPal übernommen und in plentymarkets abgespeichert.

## 1.1.2 (2017-09-25)

### Gefixt

- PayPal Wall wird neu gerendert, wenn bestimmte Events im Checkout getriggert werden.

## 1.1.1 (2017-09-20)

### Gefixt

- Fehler beim Laden der Finanzierungskosten.
- Richtige Hausnummer verwendet bei Straßen mit Leerzeichen.

## 1.1.0 (2017-09-01)

### Hinzugefügt

- Einstellungen für **Infoseite** wurden hinzugefügt.
- Einstellungen für **Beschreibung** wurden hinzugefügt.
- Es wurde eine Methode hinzugefügt, um festzulegen, ob ein Kunde von dieser Zahlungsart auf eine andere wechseln kann.
- Es wurde eine Methode hinzugefügt, um festzulegen, ob ein Kunde von einer anderen Zahlungsart auf diese wechseln kann.
- Es besteht nun die Möglichkeit, die Zahlung aus dem **Mein Kont**-Bereich neu auszuführen (PayPal/PayPal PLUS).
- Es besteht nun die Möglichkeit, die Zahlung in der Bestellbestätigung neu auszuführen (PayPal/PayPal PLUS).
- Das Logo der Zahlungsart wurde hinzugefügt und kann nun im Webshop, z.B. auf der Startseite von **Ceres**, angezeigt werden.

### Geändert

- Aufpreise der Zahlungsart wurden entfernt.

### Gefixt

- Bei Abbruch der Zahlung wird der Kunde nun auf den Checkout zurückgeleitet und nicht auf die Startseite des Shops.

### TODO

- Unter **PayPal Scripts** muss die Container-Verknüpfung von **Script loader: Register/load JS** auf **Script loader: After scripts loaded** geändert werden.

## 1.0.7 (2017-08-04)

### Gefixt
- PayPal Plus Wall funktioniert nun auch ohne zusätzliche Zahlungsarten.

## 1.0.6 (2017-07-03)

### Gefixt
- Bilderpfad für die PayPal Plus Wall

## 1.0.5

### Gefixt
- Falscher BN Code für PayPal Plus

## 1.0.4

### Gefixt
- Fallback auf Live-Modus 

### Hinzugefügt
- Dokumentation: "Zugangsdaten von PayPal erhalten"

## 1.0.3

### Gefixt
- Speichern und Löschen von Konten

## 1.0.2

### Gefixt
- PayPal-Logo wird nun aus dem korrekten Pfad geladen.
- Der Hash für die Eindeutigkeit der Zahlung wird nun korrekt berechnet.
- Rückerstattung nutzt nun den korrekten Auftrag als Basis.

## 1.0.1

### Gefixt
- Setze die Rechnungsdaten von PayPal PLUS Rechnungskauf auf die Rechnung.
- Zeige korrekte Firmendaten im Overlay von Ratenzahlung an.
- Speichern der Kontoeinstellungen.

## 1.0.0

### Hinzugefügt
- Ratenzahlung Powered by PayPal hinzugefügt.
- PayPal Plus Wall berücksichtigt andere Container.
- Ereignisaktion für Ratenzahlung Powerded by PayPal und PayPal PLUS berücksichtigen.


## 0.7.2

### Gefixt
- PayPal PLUS Wall Anzeige von externen Zahlungsarten

## 0.7.1

### Hinzugefügt
- Diverse Anpassungen in der plugin.json
- Authentifizierung für "Settings"-Routen

### Gefixt
- Kontoeinstellungen: Umgebung wird nun korrekt gespeichert.

## 0.7.0

### Funktionen
  
- **PayPal für plentymarkets**
- **PayPal PLUS für plentymarkets**