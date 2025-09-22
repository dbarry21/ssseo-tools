jQuery(function($) {
  $('#test-openai-api').on('click', function() {
    const key = $('#ssseo_openai_api_key').val();
    const resultBox = $('#openai-api-test-result').html('Testing...');

    $.post(ajaxurl, {
      action: 'ssseo_test_openai_key',
      key: key
    }, function(response) {
      if (response.success) {
        resultBox.html('<span class="text-success">' + response.data + '</span>');
      } else {
        resultBox.html('<span class="text-danger">' + response.data + '</span>');
      }
    });
  });

  $('#test-maps-api').on('click', function() {
    const key = $('#ssseo_google_static_maps_api_key').val();
    const resultBox = $('#maps-api-test-result').html('Testing...');

    $.post(ajaxurl, {
      action: 'ssseo_test_maps_key',
      key: key
    }, function(response) {
      if (response.success) {
        resultBox.html('<span class="text-success">' + response.data + '</span>');
      } else {
        resultBox.html('<span class="text-danger">' + response.data + '</span>');
      }
    });
  });
});
