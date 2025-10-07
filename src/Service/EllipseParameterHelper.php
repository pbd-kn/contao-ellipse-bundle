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
public function saveParameterSet(string $table, array $params, ?int $contentId = null): array
{
    $db = Database::getInstance();
    $result = [
        'status' => 'error',
        'id' => null,
        'message' => '',
        'exception' => null,
    ];

    try {
        // ?? Login prüfen
        $username = 'guest';
        if (defined('FE_USER_LOGGED_IN') && FE_USER_LOGGED_IN) {
            $user = \Contao\FrontendUser::getInstance();
            $username = $user->username ?? 'unknown';
        } else {
            $result['status'] = 'login_required';
            $result['message'] = 'Kein Frontend-Login vorhanden. Bitte einloggen';
            return $result;
        }

        // ?? Duplikate prüfen
        $duplicateId = $this->findDuplicate($table, $params);
        if ($duplicateId !== null) {
            $result['status'] = 'duplicate';
            $result['id'] = $duplicateId;
            $result['message'] = "Datensatz bereits vorhanden (ID: $duplicateId)";
            return $result;
        }

        // ?? Zusatzfelder
        $params['createdAt'] = time();
        $params['createdBy'] = $username;
        if ($contentId !== null) {
            $params['contentId'] = $contentId;
        }

        // ?? INSERT vorbereiten
        $columns = [];
        $placeholders = [];
        $values = [];

        foreach ($params as $key => $value) {
            if (str_starts_with($key, '_')) continue;
            $columns[] = $key;
            $placeholders[] = '?';
            $values[] = $value;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $db->prepare($sql)->execute(...$values);
        $insertId = (int) $db->insertId;

        $result['status'] = 'inserted';
        $result['id'] = $insertId;
        $result['message'] = "Datensatz erfolgreich gespeichert (ID: $insertId)";
    }
    catch (\Throwable $e) {
        // ?? Datenbankfehler oder Ausnahme
        $result['status'] = 'db_error';
        $result['message'] = 'Fehler beim Speichern in der Datenbank.';
        $result['exception'] = $e->getMessage();
    }

    return $result;
}

    /**
     * Löscht Datensätze aus der Tabelle anhand bestimmter Bedingungen.
     *
     * @param string $table Tabellenname
     * @param array  $conditions Key => Value Filter
     * @return int   Anzahl der gelöschten Datensätze
     */
    public function deleteParameterSet(string $table, array $conditions): int
    {
        $db = Database::getInstance();

        $where = [];
        $values = [];

        foreach ($conditions as $key => $value) {
            $where[] = "$key = ?";
            $values[] = $value;
        }

        $sql = sprintf('DELETE FROM %s WHERE %s', $table, implode(' AND ', $where));

        $result = $db->prepare($sql)->execute(...$values);

        return $result->affectedRows;
    }
}
