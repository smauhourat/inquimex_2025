import cpy from 'cpy';

(async () => {

    await cpy('index.html', 'dist');
    await cpy('productos.html', 'dist');
    await cpy('mercados.html', 'dist');
    await cpy('nosotros.html', 'dist');
    await cpy('contacto.html', 'dist');
    await cpy('.htaccess', 'dist');
    await cpy('sitemap.xml', 'dist');
    await cpy('robots.txt', 'dist');

})();