# Aanpassing dynamisch tarief overzicht P1-Monitor

Deze pagina is een aangepaste versie van de dynamische energietarieven pagina van P1-Monitor zonder de oorspronkelijke werking te wijzigen. 

De pagina-code ```kosten-dynamic-h.php``` is oorspronkelijk van P1-Monitor en is aangepast door mij. Deze pagina valt **NIET** onder de verantwoordelijkheid van de maker(s) van P1 Monitor (www.ztatz.nl).

**BELANGRIJK :** www.ztatz.nl en/of Security Brother bieden **GEEN** ondersteuning en zijn **NIET** verantwoordelijk voor deze code !

Maak voor het in gebruik nemen van de software altijd eerst een **volledige backup** van uw data op de P1 Monitor !

De locatie van het bestand op de Raspberry PI is : ```/p1mon/www/kosten-dynamic-h.php```

De volgende uitbreidingen/aanpassingen zijn toegevoegd

    - lichte optimalisatie van de bestaande code voor leesbaarheid en prestaties
    - behoud van de oorspronkelijke functionaliteit en schermopbouw
    - analyse van de goedkoopste aankomende periode op basis van uurblokken
    - keuze selectie voor blokken van 1 t/m 6 uur
    - visuele markering in de grafiek van:
        ◦ goedkoopste periode
        ◦ duurste periode
        ◦ daggemiddelde
        ◦ huidige tijdlijn
    - weergave van de goedkoopste aankomende periode onder de grafiek
    - opslag van gebruikerskeuzes uren blok via localStorage

<img width="999" height="732" alt="screen" src="https://github.com/user-attachments/assets/b7312ced-37d3-474f-a984-31e5d7c9f5fd" />

Afbeelding live, alle bestaande onderdelen, variabelen, logica en schermfuncties blijven intact.

Maak eerst een back-up van het originele bestand ```kosten-dynamic-h.php``` voordat u deze aangepaste versie plaatst !!!

Aanbevolen werkwijze:
    bewaar het originele bestand onder een aparte naam, bijvoorbeeld:
        ```
        kosten-dynamic-h.original.php
        ```
        of
        ```
        kosten-dynamic-h.backup.php
        ```.
    Plaats daarna pas de aangepaste versie in dezelfde map.
    Test vervolgens de pagina in de praktijk
    
Gebruik en naamsvermelding

De code van de aanpassing mag vrij worden gebruikt (start en einde aangeven in de pagina code) , aangepast en gedeeld mits naamsvermelding van de oorspronkelijke auteur behouden blijft.

**Deze code wordt beschikbaar gesteld maar zonder enige garantie.**
**Gebruik van deze code is volledig op eigen risico.**
De auteur is niet aansprakelijk voor enige directe of indirecte schade, storingen, dataverlies, foutieve metingen, verkeerde sturing of andere gevolgen die voortvloeien uit het gebruik, aanpassen of verspreiden van deze code.
