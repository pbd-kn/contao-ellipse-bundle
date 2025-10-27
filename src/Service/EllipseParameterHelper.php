<?php

namespace PbdKn\ContaoEllipseBundle\Service;

use Contao\ContentModel;
use Contao\Database;
use Symfony\Component\HttpFoundation\Request;
use PbdKn\ContaoEllipseBundle\Service\LoggerService;
use Doctrine\DBAL\Connection;

/**
 * Liest, speichert und löscht Ellipsenparameter.
 * Alle Werte werden primär aus POST gelesen (Contao-Frontend-Formulare),
 * mit Fallback auf gespeicherte Model-Werte oder Default.
 */
class EllipseParameterHelper
{
    public function __construct(
        private readonly LoggerService $logger,
        private readonly Connection $connection,
    ) {}

    // ============================================================
    // 🔹 Parameter-Gesamtset laden (POST → DB → Default)
    // ============================================================
    public function getParameterSet(Request $request, ContentModel $model, int|string $ceId, array $definitions): array
    {
        $params = [];
        $errors = [];

        foreach ($definitions as $key => $def) {
            $dbField = $def['field'] ?? null;
            $default = $def['default'] ?? null;
            $type    = $def['type'] ?? 'string';
            $min     = $def['min'] ?? null;

            $val = $this->getValue($request, $model, $ceId, $key, $dbField, $default);

            // 🔸 Typkonvertierung
            switch ($type) {
                case 'int':   $val = (int) $val; break;
                case 'float': $val = (float) str_replace(',', '.', (string) $val); break;
                case 'bool':  $val = filter_var($val, FILTER_VALIDATE_BOOLEAN); break;
                default:      $val = (string) $val;
            }

            // 🔸 Min-Prüfung
            if ($min !== null && $val < $min) {
                $errors[] = "$key muss mindestens $min sein. Wurde auf $min gesetzt.";
                $val = $min;
            }

            $params[$key] = $val;
        }

        if (!empty($errors)) {
            $params['_errors'] = $errors;
        }

        return $params;
    }

    // ============================================================
    // 🔹 Einzelwert aus POST (oder DB oder Default)
    // ============================================================
    private function getValue(Request $request, ContentModel $model, int|string $ceId, string $key, ?string $dbField, $default)
    {
        $keyWithId = $key . '_' . $ceId;
        $val = null;

        // 🟢 Priorität 1: POST
        if ($request->request->has($keyWithId)) {
            $val = $request->request->get($keyWithId);
            $this->logger->debugMe("getValue POST: $keyWithId = " . json_encode($val));
        }
        // 🟡 Fallback: GET (z. B. wenn manuell aufgerufen)
        elseif ($request->query->has($keyWithId)) {
            $val = $request->query->get($keyWithId);
            $this->logger->debugMe("getValue GET: $keyWithId = " . json_encode($val));
        }
        // 🔵 Fallback: DB-Feld
        elseif ($dbField && isset($model->$dbField) && $model->$dbField !== '') {
            $val = $model->$dbField;
            $this->logger->debugMe("getValue DB: $dbField = " . json_encode($val));
        }
        // ⚫ Fallback: Default
        else {
            $val = $default;
            $this->logger->debugMe("getValue DEFAULT: $keyWithId = " . json_encode($val));
        }

        return $val;
    }

    // ============================================================
    // 🔹 Duplikate prüfen (Doctrine-DBAL, sauberer Stringvergleich)
    // ============================================================
    public function findDuplicate(string $table, array $params): ?int
    {
        $this->logger->debugMe('findDuplicate()');

        try {
            $where  = [];
            $values = [];
            foreach ($params as $key => $value) {
                if (str_starts_with($key, '_')) { continue; }
                if (is_array($value) || is_object($value)) { $value = json_encode($value, JSON_UNESCAPED_UNICODE);}
                // Doctrine erkennt Platzhalter korrekt, wenn du ? benutzt
                $where[]  = "$key = ?";
                $values[] = $value;
            }
            if (empty($where)) { return null; }
            $sql = sprintf(
                'SELECT id FROM %s WHERE %s LIMIT 1',
                $table,
                implode(' AND ', $where)
            );
            $this->logger->debugMe('findDuplicate SQL: ' . $sql);
            // DBAL-kompatibler Aufruf
            $result = $this->connection->fetchAssociative($sql, $values);
            if ($result) {
                $this->logger->debugMe('Duplicate found with ID ' . $result['id'] . ' Info ' . $params['title']);
                return (int) $result['id'];
            }
            $this->logger->debugMe('No duplicate found.');
            return null;
        } catch (\Throwable $e) {
            $this->logger->Error('findDuplicate Exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }

    // ============================================================
    // 🔹 Parameter speichern
    // ============================================================

    public function saveParameterSet(string $table, array $params): array
    {
        $this->logger->debugMe('saveParameterSet');
        $result = [
            'status' => 'error',
            'id' => null,
            'message' => '',
            'exception' => null,
        ];
        try {
            // 🧑‍💻 Benutzername (Frontend)
            $username = 'gast';
            if (defined('FE_USER_LOGGED_IN') && FE_USER_LOGGED_IN) {
                $user = \Contao\FrontendUser::getInstance();
                $username = $user->username ?? 'unknown';
            }
            // 🔍 Duplikate prüfen
            $duplicateId = $this->findDuplicate($table, $params);
            if ($duplicateId !== null) {
                return [ 'status' => 'duplicate', 'id' => $duplicateId, 'message' => "Keine Änderungen – Darstellung (". $params['title'] .") ist schon gespeichert.",];
            }
            // 🕓 Zusatzfelder hinzufügen
            $params['createdAt'] = time();
            $params['createdBy'] = $username;
            $columns = [];
            $placeholders = [];
            $values = [];
            foreach ($params as $key => $value) {
                if (str_starts_with($key, '_')) { continue; } // interne Felder ignorieren }
                if (is_array($value) || is_object($value)) { $value = json_encode($value, JSON_UNESCAPED_UNICODE); }
                $columns[] = $key;
                $placeholders[] = '?';
                $values[] = $value;
            }
            if (empty($columns)) { throw new \RuntimeException('Keine gültigen Parameter zum Speichern vorhanden.'); }
            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', $columns),
                implode(', ', $placeholders)
            );
            $this->logger->debugMe('saveParameterSet SQL: ' . $sql, [
                'columns' => $columns,
                'count_columns' => count($columns),
                'count_values' => count($values),
            ]);
            // 💾 Insert mit Doctrine DBAL
            $this->connection->executeStatement($sql, $values);
            // Insert-ID holen
            $insertId = (int) $this->connection->lastInsertId();
            return [
                'status' => 'inserted',
                'id' => $insertId,
                'message' => "Datensatz erfolgreich gespeichert InsertID (#$insertId).",
            ];
        } catch (\Throwable $e) {
            $this->logger->Error('saveParameterSet Exception: ' . $e->getMessage(), [ 'file' => $e->getFile(), 'line' => $e->getLine(), ]);
            return [
                'status' => 'db_error',
                'message' => 'Fehler beim Speichern: ' . $e->getMessage(),
                'exception' => sprintf(
                    '%s in %s:%d',
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ),
            ];
        }
    }


    // ============================================================
    // 🔹 Parameter laden
    // ============================================================
    public function loadParameterSet(string $table, int $variantId): array
    {
        $db = Database::getInstance();

        try {
            $variant = $db->prepare("SELECT id, createdBy, title, parameters FROM $table WHERE id=?")
                ->execute($variantId)
                ->fetchAssoc();

            if (!$variant) {
                return ['status' => 'not_found', 'message' => "Darstellung Variante mit Id $variantId nicht gefunden."];
            }

            $params = array_merge(
                [
                    'id'        => $variant['id'],
                    'createdBy' => $variant['createdBy'],
                    'title'     => $variant['title'],
                ],
                json_decode($variant['parameters'], true) ?: []
            );

            return [
                'status'     => 'loaded',
                'message'    => "Darstellung $variantId erfolgreich geladen.",
                'parameters' => $params,
                'raw'        => $variant,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'db_error',
                'message' => 'Fehler beim Laden der Parameter: ' . $e->getMessage(),
            ];
        }
    }

    // ============================================================
    // 🔹 Parameter löschen
    // ============================================================
    public function deleteParameterSet(string $table, array $conditions): array
    {
        $db = Database::getInstance();
        $result = ['success' => 'error', 'count' => 0, 'message' => '', 'exception' => null];

        try {
            if (empty($table) || empty($conditions)) {
                throw new \InvalidArgumentException('Tabelle oder Bedingungen sind leer.');
            }

            $where = [];
            $values = [];
            foreach ($conditions as $key => $value) {
                $where[] = "$key = ?";
                $values[] = $value;
            }

            $sql = sprintf('DELETE FROM %s WHERE %s', $table, implode(' AND ', $where));
            $query = $db->prepare($sql)->execute(...$values);
            $affected = $query->affectedRows;

            return [
                'success'  => $affected > 0 ? 'deleted' : 'not_found',
                'count'   => $affected,
                'message' => $affected > 0
                    ? "Es wurden $affected Datensatz/Datensätze gelöscht."
                    : "Keine passenden Datensätze gefunden.",
            ];
        } catch (\Throwable $e) {
            return [
                'success' => 'db_error',
                'message' => 'Fehler beim Löschen: ' . $e->getMessage(),
                'exception' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ];
        }
    }

    // ============================================================
    // 🔹 Alle gespeicherten Varianten abrufen
    // ============================================================
    public function getSavedVariants(string $table): array
    {
        $db = Database::getInstance();

        try {
            $result = $db->query("SELECT id, createdBy, title, parameters FROM $table ORDER BY createdAt DESC")
                ->fetchAllAssoc();

            return [
                'status'  => 'ok',
                'message' => sprintf('%d Datensätze gefunden.', count($result)),
                'items'   => $result,
            ];
        } catch (\Throwable $e) {
            return [
                'status'    => 'db_error',
                'message'   => 'Fehler beim Laden der Varianten: ' . $e->getMessage(),
                'items'     => [],
            ];
        }
    }
}
