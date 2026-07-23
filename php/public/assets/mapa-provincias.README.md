# Mapa base de provincias (N-10)

`mapa-provincias.svg` — mapa en blanco de las provincias de España con un
`<g id="ES-XX">` por provincia (código ISO 3166-2:ES), sin relleno ni color:
`App\Mapa` lo colorea por provincia según el recuento de marchas en cada
petición (ver `php/app/src/Mapa.php`).

**Origen y licencia**: adaptado de [Provinces of Spain](https://github.com/jboekesteijn/provinces-of-spain)
(Joost-Wim Boekesteijn), a su vez derivado de un mapa de
[Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Provinces_of_Spain.svg)
(Emilio Gómez Fernández & Javi C. S.). **CC BY-SA 4.0**
(https://creativecommons.org/licenses/by-sa/4.0/).

Cambios hechos aquí sobre el fichero de origen: quitado el relleno gris fijo de
cada provincia y el negro fijo de las etiquetas de texto (ambos se controlan
por CSS/clase en tiempo de petición), `viewBox` en vez de `width`/`height`
fijos, namespaces `cc:`/`dc:`/`rdf:` sin uso eliminados. La geometría de los
`<path>` no se ha tocado.
