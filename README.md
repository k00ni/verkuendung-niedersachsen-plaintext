# Verkündung Niedersachsen Plaintext

Plaintext-Varianten der Verkündungen (PDF) auf der Verkündungsplattform (https://www.verkuendung-niedersachsen.de/).

Ziel dieses Projektes ist es, die Inhalte der Verkündungsplattform, sogenannte Verkündungen, maschinell besser zugänglich zu machen.
Aktuell werden die Verkündungen nur als PDF bereitgestellt.
Das Lesen einer PDF benötigt entsprechende Werkzeuge und die extrahierten Texte sind teilweise noch unsauber (z.B. Tabs oder unerwartete Zeilenumbrüche).
Dieses Projekt strebt an, (wenigstens teilweise) gesäuberte und auswertbare Texte aus den PDFs bereitzustellen.

:exclamation: **Disclaimer**: Das ist ein privates Projekt und wird unentgeldlich für die Allgemeinheit bereitgestellt.

## Lizenz

Der Quellcode und die generierten Inhalte stehen unter der [Creative Commons Attribution 4.0 Lizenz](https://creativecommons.org/licenses/by/4.0/deed.de).

## Hinweise zur Nutzung

### Metadaten in CSV-Datei

![](./csv-screenshot.png)

In der [metadata.csv](./metadata.csv) sind alle Metadaten zu den erfassten Verkündungen in strukturierter Form zu finden.
Die Daten stammen direkt von der Verkündungsplattform (hier ein [Beispiel](https://www.verkuendung-niedersachsen.de/ndsgvbl/2024/94/)) und wurde nur minimal für die CSV-Nutzung angepasst.
Es wird empfohlen diese Datei als Ausgangspunkt bei einer automatisierten Auswertung zu verwenden.
Die Plaintext-Varianten der Verkündungen befinden sich im Ordner [plaintext](./plaintext).

### Je Verkündung eine Plaintext-Datei

Im Ordner [plaintext](./plaintext) ist für jede Verkündung im PDF-Format eine entsprechende Plaintext-Variante enthalten.
Der Dateiname jeder Datei ist gleich dem zugehörigen Dateinamen der PDF-Datei.
In den Metadaten findet man diesen in der Spalte `mainDocment`.

**Hier ein Beispiel:** PDF-Datei der Verkündung heißt `mbl-2024-559.pdf` und die zugehörige Plaintext-Datei heißt `mbl-2024-559.txt` und befindet sich unter [plaintext/mbl-2024-559.txt](./plaintext/mbl-2024-559.txt).

## Projektstatus

Das Projekt befindet sich noch in einem sehr frühen Status und kann daher noch Fehler in den Daten enthalten.
Ich freue mich über Feedback und auch Pull Requests, die das Projekt voranbringen.
