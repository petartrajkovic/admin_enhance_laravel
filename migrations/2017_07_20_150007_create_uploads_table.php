<?php
/**
 * Copyright (c) 2017. Mallto.Co.Ltd.<mall-to.com> All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 上传文件管理
 * Class CreateReportsTable
 */
class CreateUploadsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->increments("id");
            $table->integer('subject_id')->comment('主体id');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('CASCADE');
            $table->string("name")->nullable()->comment("文件的名字");
            $table->text("desc")->nullable()->comment("描述");
            $table->text("file")->nullable()->comment("文件地址");
            $table->unsignedInteger("admin_user_id");
            $table->foreign('admin_user_id')->references('id')->on('admin_users');
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('uploads');
    }
}
