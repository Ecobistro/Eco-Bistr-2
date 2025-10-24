<?php
require_once 'config.php';
iniciarSessao();

$usuarioLogado = getUsuarioLogado();

// Buscar receitas em destaque
$db = Database::getInstance()->getConnection();

// Se usuário logado, buscar receitas recomendadas baseadas nas preferências
if ($usuarioLogado) {
    $receitasDestaque = getReceitasRecomendadas($usuarioLogado['id'], 8);
    
    // Se não há receitas recomendadas, buscar receitas populares
    if (empty($receitasDestaque)) {
        $receitasDestaque = getReceitasPopulares(8);
    }
} else {
    // Para usuários não logados, mostrar receitas populares
    $receitasDestaque = getReceitasPopulares(8);
}

// Buscar categorias
$stmt = $db->query("SELECT * FROM categorias ORDER BY nome");
$categorias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eco Bistrô - Receitas Sustentáveis</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">ECO BISTRÔ</a>
                          
            <nav class="nav-menu">
                <a href="receitas.php" class="nav-link">🍅 RECEITAS</a>
                <?php if ($usuarioLogado): ?>
                    <a href="perfil.php" class="nav-link">@<?php echo sanitizar($usuarioLogado['nome_usuario']); ?></a>
                    <a href="nova-receita.php" class="btn btn-primary">ADICIONAR RECEITA</a>
                    <a href="logout.php" class="nav-link">SAIR</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link">🥑 LOGIN</a>
                    <a href="cadastro.php" class="btn btn-primary">🥒 CADASTRE-SE</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-left">
                <div class="hero-mascot">
                    <img src="img/logg.png" alt="Eco Bistrô Mascote" class="mascot-img">
                </div>
                <div class="hero-text">
                    <h1 class="hero-title">Seu site favorito<br>de receitas!</h1>
                    <p class="hero-subtitle">Receitas deliciosas, práticas e<br>sustentáveis</p>
                    <button class="btn-comecar" onclick="location.href='receitas.php'">COMEÇAR ></button>
                </div>
        </div>
    </section>

    <!-- Destaques da Semana -->
    <section class="destaques-section">
        <div class="container">
            <h2 class="section-title-destaque">🥕      DESTAQUES DA SEMANA    🥕</h2>
            
            <div class="carousel-wrapper">
                <button class="carousel-nav prev" onclick="scrollCarousel(-1)">&#8249;</button>
                <div class="destaques-carousel" id="carouselDestaques">
                    <?php foreach ($receitasDestaque as $receita): ?>
                        <div class="destaque-card">
                            <a href="receita.php?id=<?php echo $receita['id']; ?>">
                                <div class="destaque-imagem">
                                    <?php if ($receita['imagem']): ?>
                                        <img src="uploads/receitas/<?php echo $receita['imagem']; ?>" 
                                             alt="<?php echo sanitizar($receita['titulo']); ?>">
                                    <?php else: ?>
                                        <div class="destaque-placeholder">🍽️</div>
                                    <?php endif; ?>
                                </div>
                                <p class="destaque-nome"><?php echo sanitizar($receita['titulo']); ?></p>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-nav next" onclick="scrollCarousel(1)">&#8250;</button>
            </div>
            
         
        </div>
    </section>

    <?php if ($usuarioLogado): ?>
        <?php if (!empty($receitasDestaque)): ?>
        <section class="section" style="background: white; padding: 4rem 0;">
            <div class="container">
                <h2 class="section-title">🍽️ RECEITAS PARA VOCÊ</h2>
                <p style="text-align: center; color: var(--text-light); margin-bottom: 2rem;">
                    Receitas selecionadas especialmente para você!
                </p>
                <div class="receitas-grid">
                    <?php foreach ($receitasDestaque as $receita): ?>
                        <div class="receita-card">
                            <a href="receita.php?id=<?php echo $receita['id']; ?>" style="text-decoration: none; color: inherit;">
                                <div class="receita-imagem">
                                    <?php if ($receita['imagem']): ?>
                                        <img src="uploads/receitas/<?php echo $receita['imagem']; ?>" 
                                             alt="<?php echo sanitizar($receita['titulo']); ?>">
                                    <?php else: ?>
                                        <div class="receita-placeholder"><span>🍽️</span></div>
                                    <?php endif; ?>
                                </div>
                                <div class="receita-info">
                                    <h3><?php echo sanitizar($receita['titulo']); ?></h3>
                                    <p class="receita-autor">por @<?php echo sanitizar($receita['nome_usuario']); ?></p>
                                    <div class="receita-meta">
                                        <span>⏱️ <?php echo formatarTempo($receita['tempo_preparo']); ?></span>
                                        <span>👥 <?php echo $receita['porcoes']; ?> porções</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Categorias -->
    <section class="categorias-section">
        <div class="container">
            <h2 class="section-title-categorias">
                <span class="icon-duck">🐥</span>
                CATEGORIAS
            </h2>
            
            <div class="categorias-layout">
                <div class="categoria-blob-left">
                    <div class="categoria-item">
                        <img src="img/comidav.webp" alt="Comidas Veganas" class="categoria-img">
                        <div class="categoria-info">
                            <h3>COMIDAS VEGANAS</h3>
                            <button class="btn-categoria" onclick="location.href='categoria.php?slug=veganas'">VER ></button>
                        </div>
                    </div>
                    
                    <div class="categoria-item">
                        <img src="img/doce.jpg" alt="Doces" class="categoria-img">
                        <div class="categoria-info">
                            <h3>DOCES</h3>
                            <button class="btn-categoria" onclick="location.href='categoria.php?slug=doces'">VER ></button>
                        </div>
                    </div>
                </div>
                
                <div class="categoria-blob-right">
                    <div class="categoria-item">
                        <img src="img/salgado.jpg" alt="Salgados" class="categoria-img">
                        <div class="categoria-info">
                            <h3>SALGADOS</h3>
                            <button class="btn-categoria" onclick="location.href='categoria.php?slug=salgados'">VER ></button>
                        </div>
                    </div>
                    
                    <div class="categoria-item">
                        <img src="img/bebidas.jpg" alt="Bebidas" class="categoria-img">
                        <div class="categoria-info">
                            <h3>BEBIDAS</h3>
                            <button class="btn-categoria" onclick="location.href='categoria.php?slug=bebidas'">VER ></button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="deco-fish-cat">🐟</div>
            <div class="deco-cucumber-cat">🥒</div>
        </div>
    </section>

    <!-- Sobre -->
    <section class="sobre-section">
        <div class="container">
            <div class="sobre-content">
                <div class="sobre-mascote">
                    <img src="img/logg.png" alt="Eco Bistrô" class="mascote-sobre-img">
                </div>
                
                <div class="sobre-texto">
                    <h2 class="sobre-title">
                        <span class="icon-recycle">♻️</span>
                        <span class="icon-leaf">🌿</span>
                        Sobre a Eco Bistrô.
                    </h2>
                    
                    <p>O Eco Bistrô é um espaço acolhedor e inspirador, feito para quem ama cozinhar com afeto, consciência e criatividade. Aqui, você encontra receitas deliciosas, práticas e sustentáveis.</p>
                    
                    <p>Com uma proposta moderna e visual encantador em tons pastéis, o Eco Bistrô valoriza ingredientes naturais, combinações simples e o prazer de comer bem.</p>
                    
                    <p>Com ajuda da plataforma, usuários conseguem criar, compartilhar suas receitas, favoritar, curtir, comentar e buscar por receitas.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->

        <img src="img/cozinhar.png" alt="logo bistro" class="imagemcozi">


    <!-- Footer -->
    <footer class="footer-eco">
        <div class="footer-wrapper">
            <div class="footer-mascote-container">
                <img src="img/logg.png" alt="Eco Bistrô" class="footer-mascote">
            </div>
            
            <div class="footer-info">
                <h3 class="footer-title">Fale conosco</h3>
                
                <div class="footer-contacts">
                    <div class="contact-box">
                        <span class="contact-icon">📞</span>
                        <div>
                            <strong>TELEFONE</strong>
                            <p>(12) 3456-7890</p>
                        </div>
                    </div>
                    
                    <div class="contact-box">
                        <span class="contact-icon">📧</span>
                        <div>
                            <strong>E-MAIL</strong>
                            <p>ecobistro@gmail.com</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-veggie">
            <span class="veggie">🥕</span>
            <span class="veggie">🌽</span>
        </div>
    </footer>

    <script>
        function scrollCarousel(direction) {
            const carousel = document.getElementById('carouselDestaques');
            const scrollAmount = 250;
            carousel.scrollBy({
                left: direction * scrollAmount,
                behavior: 'smooth'
            });
        }
    </script>
</body>
</html>