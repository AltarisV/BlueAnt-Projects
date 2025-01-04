<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projekt-Details</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="navbar">
        <ul>
                <li><a href="index.php">Laufende Projekte</a></li>
                <li><a href="detailansicht.php">Projekt-Details</a></li>
        </ul>
    </div>
    <div class="content">
        <h1>Projekt-Details</h1>
        <p>Hier können detaillierte Informationen zu den Projekten angezeigt werden.</p>
        <!-- Zusätzlicher Inhalt -->
    </div>
</body>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        // Alle Navbar-Links abrufen
        const navbarLinks = document.querySelectorAll('.navbar a');

        // Die aktuelle URL der Seite abrufen
        const currentUrl = window.location.pathname;

        // Über alle Links in der Navbar iterieren
        navbarLinks.forEach(link => {
            // Wenn der Link zur aktuellen Seite führt, füge die 'active' Klasse hinzu
            if (link.href.includes(currentUrl)) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    });
</script>

</html>
