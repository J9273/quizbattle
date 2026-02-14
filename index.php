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

/* Button Base */
.join-btn {
    position: relative;
    padding: 18px 45px;
    font-size: 22px;
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
		
#span { 
	font-size: 6vw;		
		}
		
    /* Optional: Improve mobile behavior */
    @media (max-width: 480px) {
        img {
            width: 150px;
            height: 150px;
        }
    }
</style>
</head>

<body>
	<picture>
     
  <img 
    src="images/banner.png"
    alt="Multi-user quiz app"
    >
</picture>
	<button class="join-btn">Join Game</button>

</body>
</html>