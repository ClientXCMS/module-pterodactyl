<?php
return [
    "pterodactyl" => [
        
        "offline" => "Fuera de linea",
        "online" => "En linea",
        "failed" => [
            "alreadycreated"    => "No se ha podido crear el servidor porque ya está creado.",
            "changepkgexist"    => "No se ha podido modificar el paquete del servidor porque no existe.",
            "suspendexist"      => "Fallo al suspender el servidor porque no existe.",
            "unsuspendexist"    => "Fallo en la desconexión del servidor porque no existe.",
            "terminateexist"    => "No se ha podido eliminar el servidor porque no existe .",
            "satisfying"        => "Imposibilidad de encontrar nodos que satisfagan la demanda.",
            "pwdempty"          => "La contraseña no debe estar vacía.",
            "linkserver"        => "No se ha podido cambiar la contraseña porque el servidor vinculado no existe.",
            "retrieveuser"      => "Fallo en la recuperación del usuario, código de error recibido : %status_code%",
            "changepwd"         => "Cambio de contraseña fallido, código de error recibido : %status_code%",
            "createuser"        => "Creación de usuario fallida, código de error recibido : %status_code%",
            "geteggs"           => "No se han podido obtener los datos del huevo, se ha recibido un código de error : %status_code%",
            "getserver"         => "Fallo en la obtención de datos del servidor, código de error recibido : %status_code%",
            "createserver"      => "Fallo en la creación del servidor, código de error recibido : %status_code%",
            "suspendserver"     => "Failed to suspend server, error code received : %status_code%",
            "unsuspendserver"   => "Fallo en la desconexión del servidor, código de error recibido : %status_code%",
            "terminateserver"   => "No se ha podido eliminar el servidor, se ha recibido un código de error : %status_code%",
            "buildserver"       => "Falló la actualización de la versión del servidor, código de error recibido : %status_code%",
            "startupserver"     => "La actualización del inicio del servidor ha fallado, se ha recibido un código de error : %status_code%"
        ],
        "panel" => [
            "disk" => "Espacio en disco ",
            "memory" => "Memoria",
            "button" => "Gestionar mi servidor",

            "information" => "Información sobre el alojamiento",
            "offline" => "Fuera de línea",
            "online" => "En línea",
            "start" => "Iniciar",
            "stop" => "Detener",
            "powerstop" => "Parada de emergencia",
            "restart" => "Reiniciar",
            
            "problem" => "¿Tiene problemas con el alojamiento?",
            "openticket" => "Abrir ticket",
        ],
        "adminindex" => [
            "title" => "Configuración de Pterodactyl",
            "subtitle" => "Gestionar las configuraciones de la oferta.",
            "memory"    => "Memoria",
            "disk"      => "Espacio en disco",
            "productname" => "Nombre del producto",
        ],
        "config" => [
            "title" => "Configuración del servidor",
            "subtitle" => "Gestionar las características de la oferta .",
        ],
        "form" => [
            "resources" => [
                "title"     => "Recursos",
                "memory"    => "Memoria (MB)",
                "disk"      => "Espacio en disco (MB)",
                "swap"      => "Swap",
                "io"        => "Block IO",
                "portrange" => "Puerto a asignar al servidor (Ejemplo: 3000-4000)",
                "cpu"       => "Límite de la CPU (%)",
            ],
            "features" => [
                "title" => "Características",
                "databases" => "Bases de datos para asignar al servidor ",
                "backups"   => "Backups para asignar al servidor",
            ],
            "core" => [
                "title" => "Información",
                "servername" => "Nombre del servidor",
                "egg" => "ID Huevo (egg) Pterodáctilo",
                "nest" => "ID Nido (nest) Pterodáctilo",
                "location" => "ID Ubicación Pterodactyl"
            ],
            "configurations" => [
                "title" => "Configuración",
                "image" => "Imagen Docker",
                "startup" => "Comando de inicio ",
                "oomkiller" => "Desactivado OOM Killer",
                "dedicatedip" => "Dirección IP dedicada",
            ]
        ]
    ]
];