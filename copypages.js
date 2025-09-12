import cpy from 'cpy';

(async () => {

    await cpy('index.html', 'dist');
    await cpy('productos.html', 'dist');
    await cpy('mercados.html', 'dist');
    await cpy('nosotros.html', 'dist');

})();