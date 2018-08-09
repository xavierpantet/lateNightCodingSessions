////////////////////////////////////////////////////////////
// Gère l'affichage du mot de passe pour le compte cliqué //
////////////////////////////////////////////////////////////

$('tr > .key').click(function(){
	var element = $(this).parent().find('.revealable');
	if(element.css('visibility') == 'visible'){
		element.css('visibility', 'hidden');
	}
	else{
		element.css('visibility', 'visible');
	}
});