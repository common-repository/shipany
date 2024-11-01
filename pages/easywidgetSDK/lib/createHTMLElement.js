function createHTMLElement(elementObj) {

    var element = document.createElement(elementObj.tag); 
    element.innerHTML = elementObj.innerHTML
    return element;
}
