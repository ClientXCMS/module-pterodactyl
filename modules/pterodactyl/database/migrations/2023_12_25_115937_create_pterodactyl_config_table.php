<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pterodactyl_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->integer('memory');
            $table->integer('disk');
            $table->integer('io');
            $table->integer('cpu');
            $table->integer('node_id')->nullable();
            $table->json('eggs');
            $table->integer('location_id');
            $table->unsignedBigInteger('server_id');
            $table->integer('backups')->default(0);
            $table->string('image')->nullable();
            $table->string('startup')->nullable();
            $table->boolean('dedicated_ip')->default(false);
            $table->boolean('oom_kill')->default(false);
            $table->string('server_name')->nullable();
            $table->string('server_description')->nullable();
            $table->integer('swap')->default(0);
            $table->string('port_range')->nullable();
            $table->integer('databases')->default(0);
            $table->integer('allocations')->default(0);
            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public static function blueprint(Blueprint $blueprint): string
    {
        return 'create_pterodactyl_config_table';
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pterodactyl_configs');
    }
};
