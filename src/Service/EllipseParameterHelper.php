<?php

namespace PbdKn\ContaoEllipseBundle\Service;

use Contao\ContentModel;
use Contao\Database;
use Symfony\Component\HttpFoundation\Request;

/**
 * Zentrale Klasse zur Parameterermittlung und -verwaltung (GET ? DB ? Default, Save, Delete).
 */
class EllipseParameterHelper
{
    /**
     * Liest alle gewünschten Parameter in einem Rutsch.
     *
     * @param Request      $request     Symfony Request (für GET)
     * @param ContentModel $model       Contao ContentModel
     * @param int|string   $ceId        ID des aktuellen ContentElements
     * @param array        $definitions Array ['GET-Key' => ['field' => '...', 'default' => x, 'type' => 'int', ...]]
     *
     * @return array ['A' => 400, 'B' => 200, ...]
     */
    public function getParameterSet(Request $request, ContentModel $model, int|string $ceId, array $definitions): array
    {
        $params = [];
        $errors = [];

        foreach ($definitions as $getKey => $def) {
            $dbField = $def['field'] ?? null;
            $default = $def['default'] ?? null;
            $type    = $def['type'] ?? 'string';
            $min     = $def['min'] ?? null;

            $val = $this->getValue($request, $model, $ceId, $getKey, $dbField, $default);

            // Typkonvertierung
            switch ($type) {
                case 'int':
                    $val = (int) $val;
                    break;
                case 'float':
                    $val = (float) str_replace(',', '.', (string) $val);
                    break;
                case 'bool':
                    $val = filter_var($val, FILTER_VALIDATE_BOOLEAN);
                    break;
                default:
                    $val = (string) $val;
            }

            // Min-Prüfung (optional)
            if ($min !== null && $val < $min) {
                $errors[] = "$getKey muss = $min sein. Wurde auf $min gesetzt.";
                $val = $min;
            }

            $params[$getKey] = $val;
        }

        // Fehler optional mitliefern
        if (!empty($errors)) {
            $params['_errors'] = $errors;
        }

        return $params;
    }

    /**
     * Einzelwert aus GET ? DB ? Default ermitteln.
     */
    private function getValue(Request $request, ContentModel $model, int|string $ceId, string $getKey, ?string $dbField, $default)
    {
        $keyWithId = $getKey . '_' . $ceId;
        $fromGetWithId = $request->query->get($keyWithId);
        if ($fromGetWithId !== null && $fromGetWithId !== '') {
            return $fromGetWithId;
        }

        if ($dbField && isset($model->$dbField) && $model->$dbField !== '') {
            return $model->$dbField;
        }

        return $default;
    }

    // ------------------------------------------------------------------------
    // ?? Speicherfunktionen
    // ------------------------------------------------------------------------

    /**
     * Prüft, ob ein Datensatz mit exakt gleichen Parametern bereits existiert.
     */
    public function findDuplicate(string $table, array $params): ?int
    {
        $db = Database::getInstance();

        $where = [];
        $values = [];

        foreach ($params as $key => $value) {
            if (str_starts_with($key, '_')) continue; // interne Felder ignorieren
            $where[] = "$key = ?";
            $values[] = $value;
        }

        $sql = "SELECT id FROM $table WHERE " . implode(' AND ', $where) . " LIMIT 1";
        $result = $db->prepare($sql)->execute(...$values);

        return $result->numRows > 0 ? (int) $result->id : null;
    }

    /**
     * Speichert einen Parametersatz in der angegebenen Tabelle.
     *
     * @param string $table  Tabellenname (z.B. 'tl_ellipse_data')
     * @param array  $params Key => Value Liste der Spalten
     * @return int   Neue ID
     */
    public function saveParameterSet(string $table, array $params): array
    {
        $db = Database::getInstance();
        $result = [
            'status' => 'error',
            'id' => null,
            'message' => '',
            'exception' => null,
        ];
//die("saveParameterSet " .var_dump($params));
        try {
            // 🧩 Frontend-Login prüfen
            $username = 'guest';
            if (defined('FE_USER_LOGGED_IN') && FE_USER_LOGGED_IN) {
                $user = \Contao\FrontendUser::getInstance();
                $username = $user->username ?? 'unknown';
            } else {
                // Falls kein Login vorhanden ist, trotzdem speichern (optional)
                $username = 'gast';
            }
            $valuesString = implode(', ', array_map(fn($k, $v) => "$k=$v", array_keys($params), $params));
            // 🧩 Duplikate prüfen
            $duplicateId = $this->findDuplicate($table, $params);
            if ($duplicateId !== null) {
                return [
                    'status' => 'duplicate',
                    'id' => $duplicateId,
                    'message' => "Keine Änderungen – Darstellung ist schon gespeichert.",
                    'exception' => null,
                ];
            }
            // 🧩 Zusatzfelder ergänzen
            $params['createdAt'] = time();
            $params['createdBy'] = $username;

            // contentId nur speichern, wenn Feld existiert
            /* ohne contentid
            if ($contentId !== null && $db->fieldExists('contentId', $table)) {
                $params['contentId'] = $contentId;
            }
            */

            // 🧩 SQL-INSERT vorbereiten
            $columns = [];
            $placeholders = [];
            $values = [];
            foreach ($params as $key => $value) {
                if (str_starts_with($key, '_')) continue; // interne Felder ignorieren
// 🔹 Arrays/Objekte in JSON konvertieren
    if (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
    }                
                $columns[] = $key;
                $placeholders[] = '?';
                $values[] = $value;
            }
            if (empty($columns)) {
                throw new \RuntimeException("Keine gültigen Spalten für Insert in $table gefunden.");
            }
            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', $columns),
                implode(', ', $placeholders)
            );
            // 🧩 Query ausführen
            $db->prepare($sql)->execute(...$values);
            $insertId = (int) $db->insertId;


            return [
                'status' => 'inserted',
                'id' => $insertId,
                'message' => "Datensatz erfolgreich gespeichert $valuesString",
                'exception' => null,
            ];
        }
        catch (\Throwable $e) {
            // 🧩 Fehlerbehandlung — direkt und ohne escape()
            return [
                'status' => 'db_error',
                'id' => null,
                'message' => 'Fehler beim Speichern in der Datenbank: ' . $e->getMessage(),
                'exception' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine())
                ];
        }
    }

    public function loadParameterSet(string $table, int $variantId): array
    {
        $db = \Contao\Database::getInstance();

        try {
            // Datensatz auslesen
            $variant = $db->prepare("
                SELECT id, createdBy, title, parameters
                FROM $table
                WHERE id=?
            ")->execute($variantId)->fetchAssoc();
            if (!$variant) {
                return [
                    'status'  => 'not_found',
                    'message' => "Darstellung #$variantId nicht gefunden.",
                ];
            }

            // JSON-Parameter decodieren und mit Basisfeldern zusammenführen
            $mergedParams = array_merge(
                [
                    'id'        => $variant['id'],
                    'createdBy' => $variant['createdBy'],
                    'title'     => $variant['title'],
                ],
                json_decode($variant['parameters'], true) ?: []
            );
//die ('loadParameterSet :'.var_dump($mergedParams));
            $valuesString = implode(', ', array_map(fn($k, $v) => "$k=$v", array_keys($mergedParams), $mergedParams));
            return [
                'status'     => 'loaded',
                'message'    => "Darstellung #$variantId erfolgreich geladen ($valuesString).",
                'parameters' => $mergedParams,
                'raw'        => $variant,
            ];
        } catch (\Throwable $e) {
            return [
                'status'    => 'db_error',
                'message'   => 'Fehler beim Laden: ' . $e->getMessage(),
                'exception' => $e,
            ];
        }
    }
    /**
     * Löscht Datensätze aus der Tabelle anhand bestimmter Bedingungen.
     *
     * @param string $table Tabellenname
     * @param array  $conditions Key => Value Filter
     * @return array Ergebnisstatus mit Status, Anzahl, Message, Exception
     *             $deleteResult = $this->paramHelper->deleteParameterSet('tl_ellipse_save', [
                    'id'  => $delId,
                ]);
     */
    public function deleteParameterSet(string $table, array $conditions): array
    {
        $db = Database::getInstance();
        $result = [
            'status' => 'error',
            'count' => 0,
            'message' => '',
            'exception' => null,
        ];

        try {
            // 🧩 Sicherheitsprüfung
            if (empty($table) || empty($conditions)) {
                throw new \InvalidArgumentException('Tabelle oder Bedingungen sind leer.');
            }

            // 🧩 WHERE-Bedingungen vorbereiten
            $where = [];
            $values = [];
            foreach ($conditions as $key => $value) {
                $where[] = "$key = ?";
                $values[] = $value;
            }
            if (empty($where)) { throw new \RuntimeException('Keine gültigen Bedingungen für Löschvorgang angegeben.'); }
                // 🧩 SQL-Befehl zusammenbauen
            $sql = sprintf('DELETE FROM %s WHERE %s', $table, implode(' AND ', $where));
            // 🧩 Ausführen
            $query = $db->prepare($sql)->execute(...$values);
            $affected = $query->affectedRows;
            // 🧩 Erfolgsmeldung
            return [
                'status' => $affected > 0 ? 'deleted' : 'not_found',
                'count' => $affected,
                'message' => $affected > 0
                    ? "Es wurden $affected Datensatz/Datensätze aus '$table' gelöscht."
                    : "Keine passenden Datensätze in '$table' gefunden.",
                    'exception' => null,
            ];
        }
        catch (\Throwable $e) {
            // 🧩 Fehlerauswertung
            return [
                'status' => 'db_error',
                'count' => 0,
                'message' => 'Fehler beim Löschen aus der Datenbank: ' . $e->getMessage(),
                'exception' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ];
        }
    }
    
    public function getSavedVariants(string $table): array
    {
        $db = \Contao\Database::getInstance();

        try {
            $query = sprintf("SELECT id, createdBy, title, parameters FROM %s ORDER BY createdBy,createdAt DESC", $table);

            $result = $db->query($query)->fetchAllAssoc();

            return [
                'status'  => 'ok',
                'message' => sprintf('%d Darstellung gefunden.', count($result)),
                'items'   => $result,
            ];
        } catch (\Throwable $e) {
            return [
                'status'    => 'db_error',
                'message'   => 'Fehler beim Laden der Darstellungliste: ' . $e->getMessage(),
                'exception' => $e,
                'items'     => [],
            ];
        }
    }

}
