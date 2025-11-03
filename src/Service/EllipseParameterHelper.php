<?php

namespace PbdKn\ContaoEllipseBundle\Service;

use Contao\ContentModel;
use Contao\Database;
use Symfony\Component\HttpFoundation\Request;
use PbdKn\ContaoEllipseBundle\Service\LoggerService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Security;
use Contao\FrontendUser;


/**
 * Liest, speichert und löscht Ellipsenparameter.
 * Alle Werte werden primär aus POST gelesen (Contao-Frontend-Formulare),
 * mit Fallback auf gespeicherte Model-Werte oder Default.
 */
class EllipseParameterHelper
{
    private string $username = 'guest';
    public function __construct(
        private readonly LoggerService $logger,
        private readonly Connection $connection,
        private readonly Security $security, 
    ) 
    {
            $user = $this->security->getUser(); // ✅ aktueller Security-User
            if ($user instanceof FrontendUser) {
                $this->username = $user->username;
            }            
    }
     /**
     * Gibt eine closureFunktion zurück, die Parameter aus POST, GET, Model oder Default liest.
     *
     * @param Request $request
     * @param object  $model   Contao-Model (z. B. ContentModel)
     * @param int     $ceId    Content-Element-ID
     *
     * @return callable(string $key, string $dbField, mixed $default = null): mixed
     * $val = $this->paramHelper->makeValueResolver($request, $model, $ceId);
     * $A  = (float) $val('A', 'ellipse_x', 100);
     */
    public function makeValueResolver(Request $request, object $model, int $ceId): callable
    {
        $logger = $this->logger; // lokale Referenz, damit sie in der Closure verfügbar ist

        return function (string $key, string $dbField, $default = null) use ($request, $model, $ceId, $logger) {
            // Einheitlicher Feldname mit CE-ID (z. B. "A_27")
            $keyWithId = $key . '_' . $ceId;

            // Zugriff auf alle möglichen Quellen
            $get  = $request->query;   // GET-Parameter
            $post = $request->request; // POST-Parameter

            $logger->debugMe("key $keyWithId");

            // 1️⃣ POST
            if ($request->isMethod('POST')) {
                $fromPost = $post->get($keyWithId);
                if ($fromPost !== null && $fromPost !== '') {
                    $logger->debugMe("key $keyWithId from post $fromPost");
                    return $fromPost;
                }
            }

            // 2️⃣ GET
            $fromGet = $get->get($keyWithId);
            if ($fromGet !== null && $fromGet !== '') {
                $logger->debugMe("key $keyWithId from get $fromGet");
                return $fromGet;
            }

            // 3️⃣ DB-Feld aus dem Model
            if (isset($model->{$dbField}) && $model->{$dbField} !== '') {
                $logger->debugMe("key $keyWithId from model " . $model->{$dbField});
                return $model->{$dbField};
            }

            // 4️⃣ Default-Fallback
            $logger->debugMe("key $keyWithId from default $default");
            return $default;
        };
    }
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
    public function findDuplicate(string $table, array $params): ?array
    {
        $this->logger->debugMe('findDuplicate() – only title check');

        try {
            $title = $params['title'] ?? null;
            if ($title === null || $title === '') {
                $this->logger->Error('findDuplicate ERROR: kein Titel', [
                    'table' => $table,
                    'params' => $params
                ]);
                $resArr = [
                    'status' => 'error',
                    'id' => null,
                    'message' => 'findDuplicate ERROR: kein Titel',
                    'exception' => null,
                ];
                return $resArr; // kein Titel → keine Prüfung möglich
            }
            $sql = sprintf('SELECT id FROM %s WHERE title = ? LIMIT 1', $table);
            $this->logger->debugMe('findDuplicate SQL: ' . $sql . ' [' . $title . ']');
            $result = $this->connection->fetchAssociative($sql, [$title]);
            if ($result) {
                $mes='Doppelter Eintrag gefunden ID ' . $result['id'] . ' for title "' . $title . '"';
                $this->logger->debugMe($mes);
                $resArr = [
                    'status' => 'error',
                    'id' => (int) $result['id'],
                    'message' => $mes,
                    'exception' => null,
                ];
                return $resArr; // kein Titel → keine Prüfung möglich
            }

            $this->logger->debugMe('No duplicate found for title "' . $title . '".');
            $resArr = [
                'status' => 'ok',
                'id' => null,
                'message' => '',
                'exception' => null,
                ];
            return $resArr; // kein Titel → keine Prüfung möglich
        } catch (\Throwable $e) {
            $this->logger->Error('findDuplicate Exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $resArr = [
                'status' => 'error',
                'id' => (int) $result['id'],
                'message' => 'findDuplicate Exception: ' . $e->getMessage(),
                'exception' => null,
            ];
            return $resArr; // kein Titel → keine Prüfung möglich
        }
    }

    // ============================================================
    // 🔹 Parameter speichern
    // ============================================================

    public function saveParameterSet(string $table, array $params): array
    {
        $this->logger->debugMe('saveParameterSet');

        try {
            if ($this->username == 'guest') {
                return [ 'status' => 'noLogin', 'message' => "Sie müssen angemeldet sein um zu speichern. Nehmen Sie mit mir Kontakt auf",];
            }
            // 🔍 Duplikate prüfen
            $resArr = $this->findDuplicate($table, $params);
            if ($resArr['status'] != 'ok') {
                return [ 'status' => 'duplicate', 'message' => "Keine Änderungen – Darstellung ('". $resArr['message'] ."') ist schon gespeichert/unzulässig.",];
            }
            // 🕓 Zusatzfelder hinzufügen
            $params['erstellDatum'] = time();
            $params['ersteller'] = $this->username;
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
                'status' => 'ok',
                'id' => $insertId,
                'message' => "Datensatz erfolgreich unter User " . $this->username. "gespeichert  InsertID (#$insertId).",
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
    // 🔹 Parameter laden (Doctrine-Version)
    // ============================================================
    public function loadParameterSet(string $table, int $variantId): array
    {
        $this->logger->debugMe("loadParameterSet($table, $variantId)");
        try {
            //--------------------------------------------------
            // 🔸 1. Datensatz lesen
            //--------------------------------------------------
            $sql = sprintf(
                'SELECT id, ersteller, title, parameters FROM %s WHERE id = ?',
                $table
            );
            $variant = $this->connection->fetchAssociative($sql, [$variantId]);
            if (!$variant) {
                return [
                    'status'  => 'not_found',
                    'message' => "Darstellung mit ID $variantId wurde nicht gefunden.",
                ];
            }
            //--------------------------------------------------
            // 🔸 2. JSON-Feld decodieren
            //--------------------------------------------------
            $decodedParams = [];
            if (!empty($variant['parameters'])) {
                $decodedParams = json_decode($variant['parameters'], true);
                if (!is_array($decodedParams)) {
                    $decodedParams = [];
                }
            }
            //--------------------------------------------------
            // 🔸 3. Struktur aufbauen
            //--------------------------------------------------
            $params = array_merge(
                [
                    'id'        => (int) $variant['id'],
                    'ersteller' => $variant['ersteller'] ?? '',
                    'title'     => $variant['title'] ?? '',
                ],
                $decodedParams
            );
            return [
                'status'     => 'loaded',
                'message'    => "Darstellung #$variantId erfolgreich geladen.",
                'parameters' => $params,
                'raw'        => $variant,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('loadParameterSet Exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return [
                'status'  => 'db_error',
                'message' => 'Fehler beim Laden der Parameter: ' . $e->getMessage(),
            ];
        }
    }


    // ============================================================
    // 🔹 Parameter löschen
    // ============================================================
    public function deleteParameterSet(string $table, array $conditions): array
    {
        $this->logger->debugMe('deleteParameterSet');

        $result = ['success' => 'error', 'count' => 0, 'message' => '', 'exception' => null];
        $username = $this->username;
        try {
            if (empty($table) || empty($conditions)) {
                return [
                    'success' => 'not_found',
                    'message' => 'Tabelle oder Bedingungen sind leer.',
                    'count'   => 0,
                ];
            }

            //--------------------------------------------------
            // 🔹 1. Datensatz holen, um den Ersteller zu prüfen
            //--------------------------------------------------
            $where = [];
            $values = [];
            foreach ($conditions as $key => $value) {
                $where[] = "$key = ?";
                $values[] = $value;
            }

            $sqlCheck = sprintf(
                'SELECT id, ersteller FROM %s WHERE %s LIMIT 1',
                $table,
                implode(' AND ', $where)
            );
            $record = $this->connection->fetchAssociative($sqlCheck, $values);

            if (!$record) {
                return [
                    'success' => 'not_found',
                    'message' => 'Kein passender Datensatz gefunden.',
                    'count'   => 0,
                ];
            }

            //--------------------------------------------------
            // 🔹 2. Ersteller prüfen
            //--------------------------------------------------
            $this->logger->debugMe('deleteParameterSet');
            if ($username && isset($record['ersteller']) && $record['ersteller'] !== $username) {
                return [
                    'success' => 'forbidden',
                    'message' => sprintf(
                        'Datensatz kann nur vom Ersteller "%s" gelöscht werden.',
                        $record['ersteller']
                    ),
                    'count' => 0,
                ];
            }

            //--------------------------------------------------
            // 🔹 3. Löschen ausführen
            //--------------------------------------------------
            $sqlDelete = sprintf('DELETE FROM %s WHERE %s', $table, implode(' AND ', $where));
            $affected = $this->connection->executeStatement($sqlDelete, $values);

            return [
                'success'  => $affected > 0 ? 'deleted' : 'not_found',
                'count'    => $affected,
                'message'  => $affected > 0
                    ? "Datensatz wurde erfolgreich gelöscht."
                    : "Keine passenden Datensätze gefunden.",
            ];
        } catch (\Throwable $e) {
            return [
                'success'   => 'db_error',
                'message'   => 'Fehler beim Löschen: ' . $e->getMessage(),
                'exception' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ];
        }
    }

    // ============================================================
    // 🔹 Alle gespeicherten Varianten abrufen
    // ============================================================
    public function getSavedVariants(string $table, ?string $typ = null, ?string $ersteller = null): array
    {
        try {
            // 🔹 WHERE-Bedingungen dynamisch aufbauen
            $where  = [];
            $values = [];
            if (!empty($typ)) {
                $where[]  = 'typ = ?';
                $values[] = $typ;
            }
            if (!empty($ersteller)) {
                $where[]  = 'ersteller = ?';
                $values[] = $ersteller;
            }
            // 🔹 SQL zusammenbauen
            $sql = "SELECT id, ersteller, erstellDatum, typ, title, parameters FROM $table";
            if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
            $sql .= ' ORDER BY erstellDatum DESC';
            // 🔹 Doctrine-Query ausführen
            $this->logger->debugMe("getSavedVariants SQL: $sql");

            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeQuery($values)->fetchAllAssociative();
            $this->logger->debugMe(sprintf('%d Datensätze gefunden.', count($result)));
            return [
                'status'  => 'ok',
                'message' => sprintf('%d Datensätze gefunden.', count($result)),
                'items'   => $result,
            ];
        } catch (\Throwable $e) {
            $this->logger->debugMe('Fehler beim Laden der Varianten: ' . $e->getMessage());
            return [
                'status'  => 'db_error',
                'message' => 'Fehler beim Laden der Varianten: ' . $e->getMessage(),
                'items'   => [],
            ];
        }
    }
}
