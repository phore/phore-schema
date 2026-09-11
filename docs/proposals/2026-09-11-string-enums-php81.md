# String-Enums mit PHPDoc-Beschreibungen ab PHP 8.1

| Datum | Benutzername | Kurzbeschreibung |
|---|---|---|
| 2026-09-11 | dermatthes | §§ 1–6: Entwurf und direkt beauftragte Implementierung angelegt |

## § 1 Ziel und Versionsentscheidung

String-backed PHP-Enums werden in abstrakten Schemata und JSON-Schemata einschließlich ihrer Werte und vorhandenen Case-PHPDocs abgebildet. Der Nutzer hat die Mindestversion auf die erste PHP-Version mit Enum-Unterstützung präzisiert: PHP >=8.1. Die ursprüngliche Annahme PHP 8.5 entfällt. composer.json wird deshalb von >=8.3 auf >=8.1 geändert; es wird kein Paketrelease getaggt.

Die Tests laufen in CI auf PHP 8.1 und 8.5. Die PHPUnit-Constraint lässt passende Versionen 10.5 bis 13 zu. Die vorhandene readonly-Testklasse verwendet für PHP 8.1 stattdessen readonly-Properties. Es werden keine bestehenden Produktionsklassen umgebaut.

## § 2 Modell und Parser

EnumSchemaType enthält className, backingType string, description, tags und eine cases-Liste in Deklarationsreihenfolge. Jeder Case enthält name, den exakten String-Backing-Wert, description und tags. Die Case-Liste vermeidet die Umdeutung numerischer String-Werte in Array-Schlüssel. ReflectionEnum und der vorhandene DocBlockParser liefern die Daten.

TypeParser erkennt Enums für native Typen und bereits auflösbare PHPDoc-Namen einschließlich Listen und Maps. Ein generisches PHPDoc string überschreibt eine native Enum-Einschränkung nicht; andere widersprüchliche PHPDoc-Typen werden explizit abgelehnt. Native nullable Typen behalten null als eigenen Union-Zweig.

Integer-backed, Unit- und leere Enums werden explizit abgelehnt. String-Literal-Unions und neue @enum-Tags sind nicht Teil dieser Implementierung. Die bestehende PHPDoc-Namensauflösung wird beibehalten: vollqualifizierte Namen oder Namen im selben Namespace verwenden; eine neue Auflösung importierter PHPDoc-Aliase ist nicht enthalten.

## § 3 PHPDoc und JSON-Ausgabe

Beispiel:

```php
enum Status: string
{
    /** Noch nicht veröffentlicht. */
    case Draft = 'draft';

    /**
     * Öffentlich sichtbar.
     * @deprecated Nur für bestehende Datensätze.
     */
    case Published = 'published';

    case Archived = 'archived';
}

class Article
{
    /** Veröffentlichungsstatus. */
    public Status $status;
}
```

Das Feld erhält type string und enum mit den Backing-Werten. Sobald Case-Metadaten existieren, bildet anyOf jeden Wert mit einem einelementigen enum und optionaler description ab. Fehlende Beschreibungen werden ausgelassen. Die Feldbeschreibung bleibt auf Feldebene; die Beschreibung der Enum-Klasse ist der Fallback.

```json
{
  "type": "string",
  "enum": ["draft", "published", "archived"],
  "description": "Veröffentlichungsstatus.",
  "anyOf": [
    {"enum": ["draft"], "description": "Noch nicht veröffentlicht."},
    {
      "enum": ["published"],
      "description": "Öffentlich sichtbar.\n\n@deprecated Nur für bestehende Datensätze.",
      "deprecated": true
    },
    {"enum": ["archived"]}
  ]
}
```

Alle vom vorhandenen DocBlockParser erfassten Case-Tags bleiben strukturiert im abstrakten Modell erhalten und werden im JSON zusätzlich als Text in der jeweiligen description ausgegeben. Tags sind Dokumentation, keine ausführbaren Anweisungen oder frei übernommenen JSON-Keywords. Im Standardprofil wird @deprecated zusätzlich als deprecated true ausgegeben. Minimal und OpenAiStructuredOutput lassen dieses Keyword weg, bewahren aber seinen Text. Die bestehende Behandlung mehrzeiliger Tag-Fortsetzungen durch DocBlockParser bleibt unverändert.

Nullable Enums verwenden einen eigenen null-Zweig. Listen enthalten das Enum unter items, Maps unter additionalProperties. Enum-Defaults werden für JSON zu Backing-Werten normalisiert, auch innerhalb von Arrays; das PHP-Modell bewahrt Enum-Instanzen. Im OpenAI-Profil werden Defaults weiterhin ausgelassen. Eine tatsächliche OpenAI-API-Annahme wurde nicht getestet; die bestehenden Profilgrenzen etwa bei Maps bleiben bestehen.

## § 4 Validierung und Hydration

Validator akzeptiert exakt erlaubte Strings und Instanzen der richtigen Enum-Klasse. Es findet keine Konvertierung von Integern oder anderen Typen statt. Hydrator erzeugt für gültige Strings die passenden Enum-Cases und übernimmt passende Instanzen. Fehler enthalten den bestehenden Feldpfad, auch in Listen und Maps. Constructor Promotion, Defaults und nullable Felder werden über die bestehenden rekursiven Abläufe unterstützt. Die öffentliche hydrate-Signatur bleibt unverändert.

## § 5 Implementierung und Tests

| Datei | Inhalt |
|---|---|
| src/Schema/Type/EnumSchemaType.php | Enum-Modell, Reflection und strikte Werteprüfung |
| src/Parser/TypeParser.php | Enum-Erkennung |
| src/Parser/SchemaParser.php | Native Enum-Constraints gegen überschreibende PHPDocs bewahren |
| src/Generator/JsonSchema/JsonClassSchemaGenerator.php | Enum-Ausgabe, Case-Metadaten und Defaults |
| src/Validator/Validator.php | Enum-Validierung |
| src/Hydrator/Hydrator.php | String-zu-Case-Hydration |
| test/EnumSchemaTest.php | Parser, alle Profile, Validierung, Hydration und Fehlerfälle |
| test/JsonClassSchemaGeneratorTest.php | PHP-8.1-kompatible readonly-Testfixture |
| composer.json | PHP >=8.1 und passende PHPUnit-Versionen |
| .github/workflows/tests.yml | PHP-8.1-/8.5-Matrix und benötigte PHPUnit-Extensions |
| .ai-usage-info.md | Nutzungsdokumentation |

Regressionstests prüfen insbesondere Backing-Werte ungleich Case-Namen, mehrzeiligen Freitext, Tags, fehlende PHPDocs, "0" und "", nullable Felder, Listen, Maps, Promotion, Defaults, richtige/falsche Enum-Instanzen, ungültige Strings und Pfadfehler sowie Integer-, Unit- und leere Enums.

## § 6 Prüfstatus und Quellen

Lokal sind PHP und Composer nicht installiert. Die PHPUnit-Ausführung erfolgt daher über die gepushte GitHub-CI; deren Ergebnis ist im PR zu prüfen. JSON-Beispiele, Konfiguration und tatsächlicher Commit-Diff werden vor Übergabe kontrolliert.

- [Untersuchter Ausgangsstand](https://github.com/phore/phore-schema/tree/558555ccc5355bfde9943977d80856d8e6fe0433)
- [PHP-Enum-Reflection seit PHP 8.1](https://www.php.net/manual/en/class.reflectionenumbackedcase.php)
- [PHPUnit 10 benötigt PHP 8.1](https://docs.phpunit.de/en/10.5/installation.html)
- [JSON Schema enum](https://json-schema.org/understanding-json-schema/reference/enum)
