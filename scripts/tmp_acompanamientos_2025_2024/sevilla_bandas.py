# -*- coding: utf-8 -*-
"""El Llamador nombra a las bandas de Sevilla en forma corta y sin decir el
estilo ('Rosario de Cádiz', 'Las Cigarreras'), así que la clasificación
CCTT/AM no se puede deducir del texto: hay que mapearla a mano.

Cada entrada: patrón (en minúsculas, sin acentos) -> nombre completo o None
(None = fuera de alcance: banda de música, capilla, coro, silencio...).
Los nombres completos siguen el estilo de la tabla `banda` del proyecto.
"""
DENTRO = {
    'rosario de cadiz': 'Banda de Cornetas y Tambores Nuestra Señora del Rosario de Cádiz',
    'tres caidas': 'Banda de Cornetas y Tambores Santísimo Cristo de las Tres Caídas de Triana',
    'cigarreras': 'Banda de Cornetas y Tambores Nuestra Señora de la Victoria (Las Cigarreras)',
    'centuria romana macarena': 'Banda de Cornetas y Tambores de la Centuria Romana Macarena',
    'centuria macarena': 'Banda de Cornetas y Tambores de la Centuria Romana Macarena',
    'centuria juvenil': 'Banda de Cornetas y Tambores Juvenil Centuria Romana Macarena',
    'juvenil de la centuria': 'Banda de Cornetas y Tambores Juvenil Centuria Romana Macarena',
    'sol': 'Banda de Cornetas y Tambores Nuestra Señora del Sol',
    'sagrada columna y azotes': 'Banda de Cornetas y Tambores Sagrada Columna y Azotes',
    'columna y azotes': 'Banda de Cornetas y Tambores Sagrada Columna y Azotes',
    'pasion de cristo': 'Banda de Cornetas y Tambores Pasión de Cristo',
    'esencia': 'Banda de Cornetas y Tambores Esencia',
    'cristo de la sangre': 'Banda de Cornetas y Tambores Santísimo Cristo de la Sangre',
    'nazareno de huelva': 'Banda de Cornetas y Tambores Jesús Nazareno de Huelva',
    'presentacion al pueblo de dos hermanas': 'Banda de Cornetas y Tambores Nuestro Padre Jesús en la Presentación al Pueblo de Dos Hermanas',
    'coronacion de campillos': 'Banda de Cornetas y Tambores Coronación de Campillos',
    'salud de cordoba': 'Banda de Cornetas y Tambores Nuestra Señora de la Salud de Córdoba',
    'fraternitas': 'Agrupación Musical Santa María de la Esperanza (Fraternitas)',
    'santa maria de la esperanza': 'Agrupación Musical Santa María de la Esperanza (Fraternitas)',
    'virgen de los reyes': 'Agrupación Musical Virgen de los Reyes',
    'juvenil virgen de los reyes': 'Agrupación Musical Juvenil Virgen de los Reyes de Sevilla',
    'redencion': 'Agrupación Musical Nuestro Padre Jesús de la Redención de Sevilla',
    'gitanos': 'Agrupación Musical Nuestro Padre Jesús de la Salud (Los Gitanos) de Sevilla',
    'angustias': 'Agrupación Musical María Santísima de las Angustias Coronada de Sevilla',
    'arahal': 'Agrupación Musical Santa María Magdalena de Arahal',
    'santa maria magdalena de arahal': 'Agrupación Musical Santa María Magdalena de Arahal',
    'pasion de linares': 'Agrupación Musical Nuestro Padre Jesús de la Pasión de Linares',
    'encarnacion': 'Agrupación Musical Nuestra Señora de la Encarnación (San Benito) de Sevilla',
    'santa maria de gracia': 'Agrupación Musical Santa María de Gracia',
    'sagrada presentacion': 'Agrupación Musical Juvenil Sagrada Presentación de Sevilla',
    'la sentencia de jerez': 'Agrupación Musical La Sentencia de Jerez de la Frontera',
    'sentencia de jerez': 'Agrupación Musical La Sentencia de Jerez de la Frontera',
    'lagrimas de san fernando': 'Agrupación Musical Lágrimas de Dolores de San Fernando',
    'nazareno de la algaba': 'Agrupación Musical Nuestro Padre Jesús Nazareno de La Algaba',
    'rocio': 'Agrupación Musical Juvenil María Santísima del Rocío de Sevilla',
    'virgen del rocio': 'Agrupación Musical Juvenil María Santísima del Rocío de Sevilla',
    'san juan evangelista': 'Banda de Cornetas y Tambores San Juan Evangelista de Sevilla',
    'nuestra senora de los angeles': 'Banda de Cornetas y Tambores Nuestra Señora de los Ángeles de Sevilla',
    'angusitas': 'Agrupación Musical María Santísima de las Angustias Coronada de Sevilla',
    'jesus nazareno de sevilla': 'Banda de Cornetas y Tambores Jesús Nazareno de Sevilla',
}
