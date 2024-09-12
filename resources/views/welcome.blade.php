<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel</title>
    <style>
        body {
            background-color: #7b1fa2;
            color: #fff;
            font-family: Arial, sans-serif;
        }

        h2 {
            text-align: center;
        }

        table {
            width: 80%;
            margin: 20px auto;
            border-collapse: collapse;
            background-color: #fff;
            color: #000;
            border-radius: 8px;
            overflow: hidden;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
        }

        thead {
            background-color: #4a148c;
            color: #fff;
        }

        tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        tbody tr:hover {
            background-color: #ddd;
        }
    </style>
</head>

<body>
    <h2>Tabela de Categorias</h2>
    <table id="categoriasTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>

    <h2>Tabela de Produtos</h2>
    <table id="produtosTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Preço</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>

    <h2>Tabela de Pedidos</h2>
    <table id="pedidosTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Preço Total</th>
                <th>Status</th>
                <th>Produtos</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
    @vite('resources/js/app.js')
</body>
<script>
    console.log('app.js');

    function sortTableById(tbody) {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a, b) => {
            const idA = parseInt(a.querySelector('td').innerText);
            const idB = parseInt(b.querySelector('td').innerText);
            return idA - idB;
        });
        rows.forEach(row => tbody.appendChild(row));
    }

    setTimeout(() => {
        console.log('Echo');
        window.Echo.channel('listagemCategorias')
            .listen('listarCategoria', (e) => {
                console.log('listarCategoria', e);
                const tbody = document.querySelector('#CategoriasTable tbody');
                const existingIds = Array.from(tbody.querySelectorAll('tr')).map(row => row.querySelector(
                    'td').innerText);

                e.forEach(categoria => {
                    if (!existingIds.includes(categoria.id.toString())) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                    <td>${categoria.id}</td>
                    <td>${categoria.nome}</td>
                `;
                        tbody.appendChild(tr);
                    }
                });
                sortTableById(tbody);
            });

        window.Echo.channel('listagemProdutos')
            .listen('listarProduto', (e) => {
                console.log('listarProduto', e);
                const tbody = document.querySelector('#ProdutosTable tbody');
                const existingIds = Array.from(tbody.querySelectorAll('tr')).map(row => row.querySelector(
                    'td').innerText);

                e.forEach(produto => {
                    if (!existingIds.includes(produto.id.toString())) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                    <td>${produto.id}</td>
                    <td>${produto.nome}</td>
                    <td>${produto.categoria.nome}</td>
                    <td>${produto.preco}</td>
                `;
                        tbody.appendChild(tr);
                    }
                });
                sortTableById(tbody);
            });

        window.Echo.channel('listagemPedidos')
            .listen('listarPedido', (e) => {
                console.log('listarPedido', e);
                const tbody = document.querySelector('#pedidosTable tbody');
                const existingIds = Array.from(tbody.querySelectorAll('tr')).map(row => row.querySelector(
                    'td').innerText);

                e.forEach(pedido => {
                    if (!existingIds.includes(pedido.id.toString())) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                    <td>${pedido.id}</td>
                    <td>${pedido.preco_total}</td>
                    <td>${pedido.estado}</td>
                    <td>${pedido.produtos.map(produto => produto.nome).join(', ')}</td>
                `;
                        tbody.appendChild(tr);
                    }
                });

                sortTableById(tbody);
            });

        window.Echo.channel('criandoCategoria')
            .listen('criarCategoria', (e) => {
                const categoria = e.categoria;
                const tbody = document.querySelector('#categoriasTable tbody');
                const existingIds = Array.from(tbody.querySelectorAll('tr')).map(row => row.querySelector(
                    'td').innerText);

                if (!existingIds.includes(categoria.id.toString())) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                <td>${categoria.id}</td>
                <td>${categoria.nome}</td>
            `;
                    tbody.appendChild(tr);
                }

                sortTableById(tbody);
            });

        window.Echo.channel('criandoProduto')
            .listen('criarProduto', (e) => {
                const produto = e.produto;
                const tbody = document.querySelector('#produtosTable tbody');
                const existingIds = Array.from(tbody.querySelectorAll('tr')).map(row => row.querySelector(
                    'td').innerText);

                if (!existingIds.includes(produto.id.toString())) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                <td>${produto.id}</td>
                <td>${produto.nome}</td>
                <td>${produto.categoria.nome}</td>
                <td>${produto.preco}</td>
            `;
                    tbody.appendChild(tr);
                }

                sortTableById(tbody);
            });

        window.Echo.channel('criandoPedido')
            .listen('criarPedido', (e) => {
                const pedido = e.pedido;
                const tbody = document.querySelector('#pedidosTable tbody');
                const existingIds = Array.from(tbody.querySelectorAll('tr')).map(row => row.querySelector(
                    'td').innerText);

                if (!existingIds.includes(pedido.id.toString())) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                <td>${pedido.id}</td>
                <td>${pedido.preco_total}</td>
                <td>${pedido.estado}</td>
                <td>${pedido.produtos.map(produto => produto.nome).join(', ')}</td>
            `;
                    tbody.appendChild(tr);
                }

                sortTableById(tbody);
            });

        window.Echo.channel('atualizandoCategoria')
            .listen('atualizarCategoria', (e) => {
                console.log('atualizarCategoria', e);
                const categoria = e.categoria;
                const tbody = document.querySelector('#categoriasTable tbody');
                let row = Array.from(tbody.querySelectorAll('tr')).find(row => row.querySelector('td')
                    .innerText == categoria.id.toString());

                if (row) {
                    row.innerHTML = `
                <td>${categoria.id}</td>
                <td>${categoria.nome}</td>
            `;
                } else {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                <td>${categoria.id}</td>
                <td>${categoria.nome}</td>
            `;
                    tbody.appendChild(tr);
                }

                sortTableById(tbody);
            });

        window.Echo.channel('atualizandoProduto')
            .listen('atualizarProduto', (e) => {
                console.log('atualizarProduto', e);
                const produto = e.produto;
                const tbody = document.querySelector('#produtosTable tbody');
                let row = Array.from(tbody.querySelectorAll('tr')).find(row => row.querySelector('td')
                    .innerText == produto.id.toString());

                if (row) {
                    row.innerHTML = `
                <td>${produto.id}</td>
                <td>${produto.nome}</td>
                <td>${produto.categoria.nome}</td>
                <td>${produto.preco}</td>
            `;
                } else {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                <td>${produto.id}</td>
                <td>${produto.nome}</td>
                <td>${produto.categoria.nome}</td>
                <td>${produto.preco}</td>
            `;
                    tbody.appendChild(tr);
                }

                sortTableById(tbody);
            });

        window.Echo.channel('atualizandoPedido')
            .listen('atualizarPedido', (e) => {
                console.log('atualizarPedido', e);
                const pedido = e.pedido;
                const tbody = document.querySelector('#pedidosTable tbody');
                let row = Array.from(tbody.querySelectorAll('tr')).find(row => row.querySelector('td')
                    .innerText == pedido.id.toString());

                if (row) {
                    row.innerHTML = `
                <td>${pedido.id}</td>
                <td>${pedido.preco_total}</td>
                <td>${pedido.estado}</td>
                <td>${pedido.produtos.map(produto => produto.nome).join(', ')}</td>
            `;
                } else {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                <td>${pedido.id}</td>
                <td>${pedido.preco_total}</td>
                <td>${pedido.estado}</td>
                <td>${pedido.produtos.map(produto => produto.nome).join(', ')}</td>
            `;
                    tbody.appendChild(tr);
                }

                sortTableById(tbody);
            });

    }, 200);
</script>

</html>
