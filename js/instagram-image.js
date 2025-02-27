document.addEventListener('DOMContentLoaded', function() {
    var editor = document.getElementById('templateEditor');
    var previewButton = document.getElementById('previewButton');
    var generateImageButton = document.getElementById('generateImageButton');
    var previewDiv = document.getElementById('instagram');

    previewButton.addEventListener('click', function(e) {
        e.preventDefault();
        var template = editor.value;
        template = template.replace(/\[TITULO]/g, instagram_image_data.post_title);
        template = template.replace(/\[IMAGEM_DESTACADA]/g, instagram_image_data.thumbnail_url);
        template = template.replace(/\[CATEGORIA]/g, instagram_image_data.category_name);
        previewDiv.innerHTML = template;
    });

    generateImageButton.addEventListener('click', function() {
        html2canvas(previewDiv, {
            width: previewDiv.clientWidth,
            height: previewDiv.clientHeight,
            scrollX: window.pageXOffset,
            scrollY: window.pageYOffset,
            useCORS: true,
            backgroundColor: '#FFFFFF'
        }).then(function(canvas) {
            var dataURL = canvas.toDataURL('image/png');

            var formData = new FormData();
            formData.append('action', 'save_instagram_image');
            formData.append('security', instagram_image_data.nonce);
            formData.append('image', dataURL);

            fetch(instagram_image_data.ajax_url, {
                method: 'POST',
                body: formData
            }).then(response => response.json()).then(response => {
                if (response.success) {
                    alert('Imagem salva! URL: ' + response.data.url);
                } else {
                    alert('Erro ao salvar a imagem: ' + response.data);
                }
            }).catch(error => console.error('Erro:', error));
        });
    });
});
