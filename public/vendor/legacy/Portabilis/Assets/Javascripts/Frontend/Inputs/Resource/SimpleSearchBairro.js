var simpleSearchNeighborhoodOptions = {
  canSearch: function () {
    return true;
  },

  autocompleteOptions: {
    source: function (request, response) {
      simpleSearch.search(this.element, request, function (results) {
        var term = $j.trim(request.term);
        var normalizedTerm = normalizeNeighborhood(term);

        var hasExactMatch = $j(results).toArray().some(function (item) {
          return normalizeNeighborhood(item.value) === normalizedTerm;
        });

        if (term && !hasExactMatch) {
          results.push({
            value: term,
            label: 'Criar Bairro: ' + term,
            createNeighborhood: true,
          });
        }

        response(results);
      });
    },

    select: function (event, ui) {
      var $element = $j(event.target);
      var $hiddenInput = $element.data('hidden-input-id');

      if (ui.item.createNeighborhood) {
        $element.val(ui.item.value);
        $hiddenInput.val(ui.item.value);
        $hiddenInput.trigger('change');

        return false;
      }

      return simpleSearch.handleSelect(event, ui);
    },
  },
};

function normalizeNeighborhood(value) {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}