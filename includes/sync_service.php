<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

use Mithun\PhpZkteco\Libs\ZKTeco;

function runDeviceSync(): array
{
    $result = ['success' => false, 'message' => '', 'stats' => ['users' => 0, 'attendance' => 0]];

    try {
        $zk = new ZKTeco(ZK_IP, ZK_PORT, false, ZK_TIMEOUT, ZK_PASSWORD, ZK_PROTOCOL);

        $connected = false;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            if ($zk->connect()) {
                $connected = true;
                break;
            }
            usleep(500000);
        }
        if (!$connected) {
            throw new Exception('Cannot connect to ZKTeco device at ' . ZK_IP . ':' . ZK_PORT . '. Check network connectivity.');
        }

        $deviceTime = $zk->getTime();

        $users = $zk->getUsers();
        // A fresh session prevents the user-list response from interfering with the attendance transfer.
        $zk->disconnect();
        if (!$zk->connect()) {
            throw new Exception('Device disconnected before attendance retrieval.');
        }

        $db = getDB();
        repairLegacyDeviceImport($db);

        $db->beginTransaction();
        try {
            foreach ($users as $user) {
                insertUser($db, $user);
            }
            $result['stats']['users'] = count($users);

            $attendance = $zk->getAttendances();
            $attendanceCount = count($attendance);
            $attendanceStmt = $db->prepare("INSERT INTO attendance (user_id, check_time, state) SELECT :user_id, :check_time, :state WHERE EXISTS (SELECT 1 FROM users WHERE id = :user_id3) AND NOT EXISTS (SELECT 1 FROM attendance WHERE user_id = :user_id2 AND check_time = :check_time2 AND state = :state2)");
            foreach ($attendance as $record) {
                insertAttendance($db, $record, $attendanceStmt);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        $result['stats']['attendance'] = $attendanceCount;

        $latestDate = $db->query("SELECT MAX(DATE(check_time)) FROM attendance WHERE user_id IN (SELECT id FROM users)")->fetchColumn();
        $result['stats']['latest_date'] = $latestDate;

        $zk->enableDevice();
        $zk->disconnect();

        $result['success'] = true;
        $result['message'] = sprintf(
            'Synced %d users and %d attendance records. Device time: %s',
            count($users),
            $attendanceCount,
            $deviceTime ?: 'unknown'
        );

    } catch (Throwable $e) {
        $result['message'] = 'Error: ' . $e->getMessage();
    }

    return $result;
}
