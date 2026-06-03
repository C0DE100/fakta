<?php

require_once __DIR__ . '/../classes/Database.php';

$db = new Database();
$pdo = $db->getConnection();

echo "Seeding database...\n";

/*
|--------------------------------------------------------------------------
| CLEAN DATABASE
|--------------------------------------------------------------------------
*/

$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$pdo->exec("TRUNCATE TABLE invoice_items");
$pdo->exec("TRUNCATE TABLE invoices");
$pdo->exec("TRUNCATE TABLE clients");
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | SEED CLIENTS (3 TOTAL)
    |--------------------------------------------------------------------------
    */

    $clientsData = [

        [
            'type' => 'company',
            'company_name' => 'Правна Група ДООЕЛ',
            'headquarters' => 'Скопје',
            'embs' => 7012345,
            'edb' => '403123456789012',
            'manager' => 'Александар Петров'
        ],

        [
            'type' => 'individual',
            'full_name' => 'Марко Стојанов',
            'address' => 'ул. Македонија 12, Скопје',
            'embg' => 1234567890123,
            'id_card_number' => 'A1234567'
        ],

        [
            'type' => 'individual',
            'full_name' => 'Елена Георгиева',
            'address' => 'ул. Илинденска 45, Битола',
            'embg' => 2234567890123,
            'id_card_number' => 'B2345678'
        ]

    ];

    $clientIds = [];

    foreach ($clientsData as $client) {

        if ($client['type'] === 'company') {

            $stmt = $pdo->prepare("
                INSERT INTO clients 
                (type, company_name, headquarters, embs, edb, manager,
                 full_name, address, embg, id_card_number)
                VALUES
                ('company', ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)
            ");

            $stmt->execute([
                $client['company_name'],
                $client['headquarters'],
                $client['embs'],
                $client['edb'],
                $client['manager']
            ]);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO clients
                (type, company_name, headquarters, embs, edb, manager,
                 full_name, address, embg, id_card_number)
                VALUES
                ('individual', NULL, NULL, NULL, NULL, NULL, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $client['full_name'],
                $client['address'],
                $client['embg'],
                $client['id_card_number']
            ]);
        }

        $clientIds[] = $pdo->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | SERVICES
    |--------------------------------------------------------------------------
    */

    $services = [
        'Адвокатски трошоци',
        'Правен совет',
        'Состав на тужба',
        'Изработка на договор'
    ];

    /*
    |--------------------------------------------------------------------------
    | SEED INVOICES (2026 ONLY)
    |--------------------------------------------------------------------------
    */

    $year = 2026;
    $shortYear = '26';

    $counter = 1;
    $currentDate = new DateTime('2026-01-05');

    foreach ($clientIds as $clientId) {

        $invoiceCount = rand(2,3);

        for ($i = 0; $i < $invoiceCount; $i++) {

            $month = $currentDate->format('m');
            $date  = $currentDate->format('Y-m-d');

            $number = str_pad($counter,3,'0',STR_PAD_LEFT) . "-{$month}/{$shortYear}";

            $stmt = $pdo->prepare("
                INSERT INTO invoices (number, client_id, date, status)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $number,
                $clientId,
                $date,
                rand(0,1) ? 'платена' : 'испратена'
            ]);

            $invoiceId = $pdo->lastInsertId();

            $itemsCount = rand(1,3);

            for ($x = 0; $x < $itemsCount; $x++) {

                $stmtItem = $pdo->prepare("
                    INSERT INTO invoice_items (invoice_id, name, price)
                    VALUES (?, ?, ?)
                ");

                $stmtItem->execute([
                    $invoiceId,
                    $services[array_rand($services)],
                    rand(3000,15000)
                ]);
            }

            $counter++;

            // move date forward randomly (5–20 days)
            $currentDate->modify('+' . rand(5,20) . ' days');
        }
    }

    $pdo->commit();

    echo "✅ Seeded small chronological dataset successfully.\n";

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "❌ Error: " . $e->getMessage();
}