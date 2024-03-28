$(document).ready(function() {
    // URL du flux RSS à afficher
    var rssURL = "https://openai.com/blog/rss.xml";
    
    // URL du proxy CORS
    var corsProxy = "https://api.allorigins.win/raw?url=";
    
    // Récupérer et analyser le flux RSS
    $.get(corsProxy + rssURL, function(data) {
      var $xml = $(data);
      
      // Afficher les 5 premiers éléments du flux RSS
      $xml.find("item").slice(0, 5).each(function() {
        var $this = $(this),
            item = {
              title: $this.find("title").text(),
              link: $this.find("link").text(),
              description: $this.find("description").text(),
              pubDate: $this.find("pubDate").text()
            };
        
        // Afficher l'élément du flux RSS dans la page
        $("#rss-feed").append(
          '<div class="rss-item">' +
            '<h3><a href="' + item.link + '">' + item.title + '</a></h3>' +
            '<p>' + item.description + '</p>' +
            '<p><small>Publié le : ' + item.pubDate + '</small></p>' +
          '</div>'
        );
      });
    });
  });