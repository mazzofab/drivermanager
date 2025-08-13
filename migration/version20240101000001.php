<?php
namespace OCA\DriverManager\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20240101000001 extends SimpleMigrationStep {
    
    /**
     * @param IOutput $output
     * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('drivermanager_drivers')) {
            $table = $schema->createTable('drivermanager_drivers');
            
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('name', 'string', [
                'notnull' => true,
                'length' => 200,
            ]);
            $table->addColumn('surname', 'string', [
                'notnull' => true,
                'length' => 200,
            ]);
            $table->addColumn('license_number', 'string', [
                'notnull' => true,
                'length' => 50,
            ]);
            $table->addColumn('license_expiry', 'string', [
                'notnull' => true,
                'length' => 10,
            ]);
            $table->addColumn('user_id', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('created_at', 'string', [
                'notnull' => true,
                'length' => 19,
            ]);
            $table->addColumn('updated_at', 'string', [
                'notnull' => true,
                'length' => 19,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'drivermanager_user_id_index');
            $table->addIndex(['license_expiry'], 'drivermanager_expiry_index');
            
            $output->info('Created table drivermanager_drivers');
        }

        return $schema;
    }
}