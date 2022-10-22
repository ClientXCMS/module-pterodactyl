<?php
return [
    "pterodactyl" => [
        
        "offline" => "aus",
        "online" => "Aussteigen",
        "failed" => [
            "alreadycreated"    => "Server konnte nicht erstellt werden, da er bereits erstellt wurde",
            "changepkgexist"    => "Das Serverpaket konnte nicht geändert werden, da es nicht vorhanden ist",
            "suspendexist"      => "Der Server konnte nicht angehalten werden, da er nicht existiert.",
            "unsuspendexist"    => "Unsuspendierung des Servers fehlgeschlagen, da er nicht existiert.",
            "terminateexist"    => "Der Server konnte nicht gelöscht werden, da er nicht existiert.",
            "satisfying"        => "Es konnten keine Knoten gefunden werden, die die Anforderung erfüllen",
            "pwdempty"          => "Passwort darf nicht leer sein",
            "linkserver"        => "Passwort konnte nicht geändert werden, da der Verbindungsserver nicht existiert",
            "retrieveuser"      => "Benutzerwiederherstellung fehlgeschlagen, Fehlercode erhalten : %status_code%",
            "changepwd"         => "Passwort konnte nicht geändert werden, Fehlercode erhalten : %status_code%",
            "createuser"        => "Benutzer konnte nicht erstellt werden, Fehlercode erhalten : %status_code%",
            "geteggs"           => "Eierdaten konnten nicht abgerufen werden, Fehlercode erhalten : %status_code%",
            "getserver"         => "Fehler beim Abrufen von Daten vom Server, Fehlercode empfangen : %status_code%",
            "createserver"      => "Server konnte nicht erstellt werden, Fehlercode erhalten : %status_code%",
            "suspendserver"     => "Suspendierung des Servers fehlgeschlagen, Fehlercode empfangen : %status_code%",
            "unsuspendserver"   => "Unsuspendierung des Servers fehlgeschlagen, Fehlercode empfangen : %status_code%",
            "terminateserver"   => "Server konnte nicht entfernt werden, Fehlercode erhalten : %status_code%",
            "buildserver"       => "Serverversion konnte nicht aktualisiert werden, Fehlercode erhalten : %status_code%",
            "startupserver"     => "Server-Boot-Update fehlgeschlagen, Fehlercode erhalten : %status_code%"
        ],
        "panel" => [
            "disk" => "Festplattenplatz",
            "memory" => "Erinnerung",
            "button" => "Verwalten Sie meinen Server",
            
            "problem" => "Haben Sie ein Problem mit dem Hosting?",
            "openticket" => "Öffnen Sie ein Ticket!", 
            "information" => "Unterkunftsinformationen",
            
            "start" => "Zum Starten",
            "stop" => "Ausschalten",
            "powerstop" => "Töten",
            "restart" => "Neustarten",
        ],
        "adminindex" => [
            "title" => "Pterodaktylus-Setup",
            "subtitle" => "Verwalten Sie die Konfigurationen Ihrer Angebote.",
            "memory"    => "Erinnerung",
            "disk"      => "Festplattenplatz ",
            "productname"=> "Produktname",
        ],
        "config" => [
            "title" => "Server-Setup",
            "subtitle" => "Verwalten Sie die verschiedenen Merkmale des Angebots.",
        ],
        "form" => [
            "resources" => [
                "title"     => "Ressourcen",
                "memory"    => "Arbeitsspeicher (MB)",
                "disk"      => "Speicherplatz (MB)",
                "swap"      => "Tauschen",
                "io"        => "IO blockieren",
                "portrange" => "Dem Server zuzuweisender Port (Beispiel: 3000-4000)",
                "cpu"       => "CPU-Limit (%)",
            ],
            "features" => [
                "title" => "Merkmale",
                "databases" => "Datenbanken, die dem Server zugewiesen werden sollen",
                "backups"   => "Dem Server zuzuordnende Datensicherungen",
            ],
            "core" => [
                "title" => "Information",
                "servername"=> "Servername",
                "egg" => "ID Ei Flugsaurier",
                "nest" => "ID Nest Flugsaurier",
                "location" => "ID-Verleih Pterodaktylus"
            ],
            "configurations" => [
                "title" => "Aufbau",
                "image" => "Docker-Image",
                "startup" => "Befehl starten",
                "oomkiller" => "Deaktivieren Sie den OOM-Killer",
                "dedicatedip" => "Dedizierte IP-Adresse",
            ]
        ]
    ]
];
