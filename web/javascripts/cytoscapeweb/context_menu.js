function cytoscape_context_menu(agent){
  agent.cytoscape_agent('add_context_menu_item', "Select first neighbors", "nodes", function (evt) {
    var vis = agent.cytoscape_agent('vis');
    var rootNode = evt.target;
    var fNeighbors = vis.firstNeighbors([rootNode]);
    var neighborNodes = fNeighbors.neighbors;
    vis.select([rootNode]).select(neighborNodes);
  });

  agent.cytoscape_agent('add_context_menu_item', "Remove", "nodes", function (evt) {
    var vis = agent.cytoscape_agent('vis');
    var node = evt.target;
    agent.cytoscape_agent('remove_entities', node.data.entity_type, [node.data.id])
    agent.cytoscape_agent('draw');
  });

  agent.cytoscape_agent('add_context_menu_item', "Remove selected", "none", function (evt) {
    var vis = agent.cytoscape_agent('vis');
    var removed_nodes = vis.selected('nodes')
    $.map(removed_nodes, function(node){
      agent.cytoscape_agent('remove_entities', node.data.entity_type, [node.data.id])
    })
    agent.cytoscape_agent('draw');
  });

  agent.cytoscape_agent('add_context_menu_item', "Log info", "nodes", function (evt) {
    var vis = agent.cytoscape_agent('vis');
    var node = evt.target;

    console.log("Node: " + node.data.label);
    for (var i in node.data) {
      var variable_name = i;
      var variable_value = node.data[i];
      console.log( "event.target.data." + variable_name + " = " + variable_value );
    }

  });


}
