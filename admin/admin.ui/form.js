var app = angular.module('TeapotApp')

app.run(function(formlyConfig) {
    formlyConfig.setType({
        name: 'input',
        template: '<input ng-model="model[options.key]">'
    })
    
    formlyConfig.setType({
        name: 'checkbox',
        template: '<md-checkbox ng-model="model[options.key]">{{to.label}}</md-checkbox>'
    })

    formlyConfig.setType({
        name: 'textarea',
        template: '<textarea ng-model="model[options.key]"></textarea>'
    })
    
    formlyConfig.setType({
        name: 'image',
        templateUrl: 'form.image.html',
        controller: function($scope, $http) {
            // XXX: add support for required
            // XXX: add error handling if upload fails
            $scope.fileSelected = function (files) {
                if (files && files.length == 1) {
                    var filename = files[0].name
                    
                    var fd = new FormData()
                    fd.append('file', files[0])
                    $http.post(
                        'api/v1/attachments/' + filename,
                        fd,
                        {
                            transformRequest: angular.identity,
                            headers: {'Content-Type': undefined}
                        }
                    ).then(function () {
                        /* show the new image */
                        $scope.model[$scope.options.key] = filename
                    })
                }
            }

            $scope.newFile = function ($event) {
                /* the target is the image */
                var input = angular.element($event.target).next()[0]
                window.setTimeout(function() {
                    input.click()
                }, 0)
            }
        }
    })

    formlyConfig.setWrapper({
        name: 'mdLabel',
        types: ['input', 'textarea'],
        template: '<label>{{to.label}}</label><formly-transclude></formly-transclude>'
    })
    
    formlyConfig.setWrapper({
        name: 'mdInputContainer',
        types: ['input', 'textarea'],
        template: '<md-input-container><formly-transclude></formly-transclude></md-input-container>'
    })

    // XXX: date not converting back correctly
    // XXX: disclose not right icon
    formlyConfig.setType({
        name: 'date',
        template: '<md-datepicker ng-model="model[options.key]" md-placeholder="Enter date"></md-datepicker>',
        controller: function ($scope) {
            $scope.model[$scope.options.key] = new Date($scope.model[$scope.options.key])
        }
    })

    // XXX: add url input type
    // XXX: add price input validator
    // XXX" add gallery input validator

    // XXX: sort this so you can re-order and it doens't use randoms
    var unique = 1
    formlyConfig.setType({
        name: 'array',
        templateUrl: 'form.array.html',
        controller: function($scope) {
            $scope.formOptions = {formState: $scope.formState};
            $scope.addNew = addNew;
            
            $scope.copyFields = copyFields;
            
            
            function copyFields(fields) {
                fields = angular.copy(fields);
                addRandomIds(fields);
                return fields;
            }
            
            function addNew() {
                $scope.model[$scope.options.key] = $scope.model[$scope.options.key] || [];
                var repeatsection = $scope.model[$scope.options.key];
                var lastSection = repeatsection[repeatsection.length - 1];
                var newsection = {};
                if (lastSection) {
                    newsection = angular.copy(lastSection);
                }
                repeatsection.push(newsection);
            }
            
            function addRandomIds(fields) {
                unique++;
                angular.forEach(fields, function(field, index) {
                    if (field.fieldGroup) {
                        addRandomIds(field.fieldGroup);
                        return; // fieldGroups don't need an ID
                    }
                    
                    if (field.templateOptions && field.templateOptions.fields) {
                        addRandomIds(field.templateOptions.fields);
                    }
                    
                    field.id = field.id || (field.key + '_' + index + '_' + unique + getRandomInt(0, 9999));
                });
            }
            
            function getRandomInt(min, max) {
                return Math.floor(Math.random() * (max - min)) + min;
            }
        }
    });

    // having trouble getting icons to work.
    // Feel free to clone this jsbin, fix it, and make a PR to the website repo: https://github.com/formly-js/angular-formly-website
    // formlyConfig.templateManipulators.preWrapper.push(function(template, options) {
    //   if (!options.data.icon) {
    //     return template;
    //   }
    //   return '<md-icon class="step" md-font-icon="icon-' + options.data.icon + '"></md-icon>' + template;
    // });
})


app.controller('FormController', function ($scope, $http, response) {
    /* data for the form */
    $scope.model = response.data.model
    if ($scope.model == null) {
      $scope.model = {} /* ensure we always have a dict */
    }
    $scope.fields = response.data.fields
    $scope.originalModel = angular.copy($scope.model)
    $scope.saveRequired = false
    $scope.options = {}

    /* setup watch to detect when model changes */
    $scope.$watch(
      'model',
      function (newValue, oldValue) {
        /* as some values need converting do real objects e.g. Date
         * convert first to JSOn and back to do comparison */
        n = JSON.parse(JSON.stringify(newValue))
        o = JSON.parse(JSON.stringify(oldValue))
        if (!angular.equals(n, o)) {
          $scope.saveRequired = true
        }
      },
      true
    )

    $scope.save = function () {
      /* save the data for this form */
      console.log($scope.model)
      $http.put(
        response.config.url,
        { 'model': $scope.model },
        {
            headers: { 'Content-Type': 'application/teapot.form+json' }
        }
      ).then(function (response) {
          /* update stashed model */
          $scope.originalModel = angular.copy($scope.model)
          $scope.saveRequired = false

      })
      // XXX: error handling
    }
})
