<?php

namespace App\Enums;

enum Role: string
{
    case User = 'pengguna';
    case Admin = 'admin';
    case Courier = 'kurir';
    case SuperAdmin = 'super_admin';
}
