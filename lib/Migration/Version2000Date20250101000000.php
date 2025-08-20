<?php

declare(strict_types=1);

namespace OCA\DriverManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2000Date20250101000000 extends SimpleMigrationStep {

    /**
     * @param IOutput $output
     * @param Closure(): ISchemaWrapper $schemaClosure
     * @param array $options
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if (!$schema->hasTable('drivermanager_drivers')) {
            $table = $schema->createTable('drivermanager_drivers');
            
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('name', Types::STRING, [
                'notnull' => true,
                'length' => 200,
            ]);
            $table->addColumn('surname', Types::STRING, [
                'notnull' => true,
                'length' => 200,
            ]);
            $table->addColumn('license_number', Types::STRING, [
                'notnull' => true,
                'length' => 50,
            ]);
            $table->addColumn('license_expiry', Types::STRING, [
                'notnull' => true,
                'length' => 10,
                'comment' => 'Date in YYYY-MM-DD format'
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('created_at', Types::STRING, [
                'notnull' => true,
                'length' => 19,
                'comment' => 'Timestamp in YYYY-MM-DD HH:MM:SS format'
            ]);
            $table->addColumn('updated_at', Types::STRING, [
                'notnull' => true,
                'length' => 19,
                'comment' => 'Timestamp in YYYY-MM-DD HH:MM:SS format'
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'drivermanager_user_id_idx');
            $table->addIndex(['license_expiry'], 'drivermanager_expiry_idx');
            $table->addIndex(['license_number'], 'drivermanager_license_idx');
            
            $output->info('Created table drivermanager_drivers');
        }

        return $schema;
    }
}
