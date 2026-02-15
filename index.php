<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizBattle</title>
	<style>
    /* Remove default spacing */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* Full screen container */
		/*
    body {
        height: 100vh;
        display: flex;
        justify-content: center;  
        align-items: center;      
        background-color: #f4f4f4;
    }
*/
	body {
    min-height: 100vh;
    display: grid;
    place-items: center;
	font-family: "Gill Sans", "Gill Sans MT", "Myriad Pro", "DejaVu Sans Condensed", Helvetica, Arial, "sans-serif"	
}

    img {
        width: 400px;
        height: 400px;
        object-fit: cover; /* Keeps image proportional */
    }
	
	/* MENU CONTAINER */
.menu {
    position: fixed;
    top: 20px;
    right: 20px;
    width: 60px;
    background: #543f97;
    border-radius: 15px;
    overflow: hidden;
    transition: width 0.4s ease;
    box-shadow: 0 0 15px rgba(0,0,0,0.5);
}

/* Expand on hover (desktop) */
.menu:hover {
    width: 200px;
}

/* Hamburger Icon */
.menu-toggle {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 60px;
    cursor: pointer;
    font-size: 24px;
}

/* Menu Items */
.menu-items {
    display: flex;
    flex-direction: column;
    opacity: 0;
    transition: opacity 0.3s ease;
}

/* Show items when expanded */
.menu:hover .menu-items {
    opacity: 1;
}

.menu a {
    text-decoration: none;
    color: white;
    padding: 15px;
    transition: background 0.3s;
}

.menu a:hover {
    background: #374151;
}

/* Button Base */
.join-btn {
    position: relative;
/*    padding: 18px 45px;	*/
	padding: 2vw 3.5vw;
    font-size: 3vw;
    font-weight: bold;
    color: white;
    background: linear-gradient(45deg, #ff0055, #ff9900, #00ccff, #9900ff);
    background-size: 300%;
    border: none;
    border-radius: 50px;
    cursor: pointer;
    overflow: hidden;
    transition: 0.3s ease;
    animation: gradientMove 4s linear infinite;
    box-shadow: 0 0 15px rgba(255, 0, 255, 0.6);
}

/* Animated Gradient */
@keyframes gradientMove {
    0% { background-position: 0%; }
    100% { background-position: 300%; }
}

/* Glow Pulse */
.join-btn::before {
    content: "";
    position: absolute;
    inset: -5px;
    border-radius: 50px;
    background: linear-gradient(45deg, #ff00cc, #3333ff, #00ffcc);
    z-index: -1;
    filter: blur(20px);
    opacity: 0.7;
    animation: pulse 2s infinite alternate;
}

@keyframes pulse {
    from { opacity: 0.6; }
    to { opacity: 1; }
}

/* Hover Effect */
.join-btn:hover {
    transform: translateY(-5px) scale(1.05);
    box-shadow: 0 0 30px rgba(255, 0, 255, 0.9);
}

/* Click Effect */
.join-btn:active {
    transform: scale(0.95);
}		
		
A { 
	color: bisque;
	text-decoration: none;
/*	font-size: 3vw;		*/
		}
footer {
		position: fixed;
		bottom: 0;
		width: 100%;
		text-align: center;
		font-size: 1vw;
	color: #8C8C8C;
		}			
		

		/* Mobile Tap Support */
@media (hover: none) {
    .menu {
        width: 60px;
    }

    .menu.active {
        width: 200px;
    }

    .menu.active .menu-items {
        opacity: 1;
    }
}
		
    /* Optional: Improve mobile behavior */
    @media (max-width: 480px) {
        img {
            width: 200px;
            height: 200px;
        }
		footer {
			font-size: 5vw;
		}

        .join-btn {
            font-size: 5vw;
            padding: 3vw 6vw;
        }

    }

		
	
</style>
</head>

<body>
	
	<div class="menu" id="menu">
    <div class="menu-toggle" onclick="toggleMenu()">☰</div>
    <div class="menu-items">
        <a href="#" data-url="https://quizbattle-9ls0.onrender.com">Home</a>
        <a href="#" data-url="https://quizbattle-9ls0.onrender.com/admin/login.php">Admin</a>
        <a href="#" data-url="https://quizbattle-9ls0.onrender.com/public/player.html">Join Game</a>
        <a href="#">Logout</a>
    </div>
</div>
	<picture>     
        <img 
        src="images/banner.png"
        alt="Multi-user quiz app"
        >
    </picture>
	<button class="join-btn"><a href="https://quizbattle-9ls0.onrender.com/public/player.html">Join Game</a></button>

	<footer>
		2026 &copy; John Ward
	</footer>
<script>
function toggleMenu() {
    document.getElementById("menu").classList.toggle("active");
}
	function openPage(url) {
    window.location.href = url;
}
	document.querySelectorAll('.menu-items a').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = this.dataset.url;
    });
});
</script>	
</body>
</html>