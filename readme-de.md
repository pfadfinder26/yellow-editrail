# Editrail 0.1.5

Eine Leiste am Rand des Fensters für Redakteur*innen. Entwickelt von Liam Perlaki.

Die [Edit-Erweiterung](https://github.com/annaesvensson/yellow-edit) setzt eine Leiste über die
Seite, die mit ihr wegscrollt. Diese hier setzt eine Leiste an den rechten Rand, die stehen bleibt:
die Knöpfe der Edit-Erweiterung, ein Seitenbaum, die Dateien der Website und ein Knopf, der das
Bearbeiten beendet.

## Eine Erweiterung installieren

[ZIP-Datei herunterladen](https://github.com/pfadfinder26/yellow-editrail/archive/refs/heads/main.zip) und in den Ordner `system/extensions` kopieren. [Mehr über Erweiterungen](https://github.com/annaesvensson/yellow-update).

## Was die Leiste kann

**Schmal oder breit:** die Leiste ist eine Spalte aus Symbolen, der oberste Knopf klappt sie auf.
Aufgeklappt sagt jeder Knopf, was er tut, und Seitenbaum und Dateien erscheinen. Die Leiste und
ihre Abschnitte bleiben so, wie sie zuletzt waren, im Browser dieser Person, eine Seite, die nach
einer Änderung neu geladen wird, sieht also aus wie vorher.

**Die Knöpfe der Edit-Erweiterung** wandern in die Leiste, Bearbeiten, Anlegen, Löschen und das
Konto sind also dort, wo alles andere ist. „+“ und „−“ bekommen ihre Namen zurück, und oben auf der
Seite springt nichts mehr herum.

**Der Seitenbaum** zeigt jede Seite der Website, auch die unlisted, kursiv. Ein Ast ist zu, außer
die offene Seite liegt darin. Unter der Maus bietet eine Seite vier Knöpfe: bearbeiten, eine Seite
darunter anlegen, zeigen oder verstecken, löschen. Der Knopf zum Zeigen und Verstecken trägt ein
offenes oder ein durchgestrichenes Auge und schreibt `Status: unlisted` in die Seite, so wie es
eine Person auch täte.

**Die Dateien** aus `media/images` stehen mit Vorschaubild und Namen da, jeder Ordner ein eigener
Ast, der sich öffnet wie die Äste des Baums. Eine Datei lässt sich
löschen, sie wandert in den Papierkorb von Yellow, nicht ins Nichts. Neue kommen über „Dateien
hinzufügen“ dazu, das übergibt sie der Edit-Erweiterung, es gelten also dieselben Regeln für Größe
und Typ wie überall. Während eine Seite bearbeitet wird, bietet jede Datei außerdem an, eingefügt
zu werden: das Markdown der Datei landet dort, wo der Cursor steht.

**Die Bausteine** sind die Seiten aus `content/shared`, die Blöcke, die auf mehr als einer Seite
stehen, Fußzeile und Briefkopf und die Vorlagen neuer Seiten. Es führt kein Link dorthin, also
stehen sie hier, und jeder geht in dem Fenster auf, das eine Seite bearbeitet, wie jede andere
Seite auch.

**Bearbeiten beenden** führt zurück auf die Seite ohne `/edit` davor, es meldet niemanden ab.

**Das Fenster, das eine Seite bearbeitet,** steht mittig neben der Leiste und bleibt offen, wenn
daneben geklickt wird, ein verrutschter Klick wirft also nicht weg, was getippt wurde.

## Was sie braucht

Die Edit-Erweiterung und jemanden, der angemeldet ist. Alles hier sehen nur Redakteur*innen. Eine
Datei zu löschen fragt den Server, der die Anmeldung der Edit-Erweiterung prüft, ihre Erlaubnis zum
Hochladen und den Token, der sagt, dass die Anfrage von dieser Website kam. Außerhalb von
`media/images` lässt sich nichts löschen, egal was eine Anfrage behauptet.

Die Farben kommen aus vier Einstellungen des Stylesheets, `--editrail-background`,
`--editrail-text`, `--editrail` und `--editrail-wide`. Ein Theme kann sie auf seine eigenen setzen.
