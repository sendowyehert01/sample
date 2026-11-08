<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSamplesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('samples', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('description', 'text', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => null])
            ->create();
    }
}
