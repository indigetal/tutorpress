document.addEventListener("DOMContentLoaded", function () {
  let tabsToRemove = ["[data-tutor-query-value='comments']", "[data-tutor-query-value='overview']"];
  tabsToRemove.forEach((selector) => {
    let tab = document.querySelector(selector);
    if (tab) {
      tab.parentNode.removeChild(tab);
    }
  });
});
