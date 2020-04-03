<?php

namespace Drupal\todo_list\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Cookie\SetCookie;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Creates a form to add Todo List.
 */
class TodoListForm extends FormBase {

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * To Do list tasks.
   *
   * @var array
   */
  protected $todoTasks;

  /**
   * TodoListForm constructor.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   */
  public function __construct(CurrentPathStack $current_path, RequestStack $requestStack) {
    $this->currentPath = $current_path;
    $this->requestStack = $requestStack->getCurrentRequest();
    $this->todoTasks = $this->getTodoTasks();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('path.current'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'todo_list_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $trigger_element = $form_state->getTriggeringElement();
    $filter = $form_state->getValue('filters') ?? 'all';

    // Update Tasks list when marked/removed from completed list.
    if ($trigger_element && $trigger_element['#type'] == 'checkbox') {
      $trigger_element['#name'] == 'select_all' ?
      array_walk($this->todoTasks, [$this, 'updateTasksList'], $trigger_element['#value']) :
      $this->todoTasks[$trigger_element['#parents'][1]]['completed'] = $trigger_element['#value'];

      $this->saveTaskList();
      $user_input = $form_state->getUserInput();
      unset($user_input['tasks']);
      unset($user_input['select_all']);
      $form_state->setUserInput($user_input);
    }

    $completed_tasks = array_filter($this->todoTasks, [$this, 'completedTasks']);

    $form['todo_list_wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="todo-list-wrapper" class="todoapp">',
      '#suffix' => '</div>',
      'select_all' => [
        '#prefix' => '<header class="header"><h1>todos</h1><div class="add-new-task">',
        '#type' => 'checkbox',
        '#default_value' => count($completed_tasks) == count($this->todoTasks),
        '#ajax' => [
          'callback' => '::updateTask',
          'wrapper' => 'todo-list-wrapper',
        ],
      ],
      'new_todo' => [
        '#type' => 'textfield',
        '#attributes' => [
          'placeholder' => $this->t('What needs to be done?'),
          'class' => ['new-todo'],
          'autocomplete' => 'off',
        ],
      ],
      'add_task' => [
        '#type' => 'submit',
        '#value' => $this->t('Add Task'),
        '#name' => 'add-task',
        '#ajax' => [
          'callback' => '::updateTask',
          'wrapper' => 'todo-list-wrapper',
        ],
        '#suffix' => '</div></header>',
      ],
    ];

    $form['todo_list_wrapper']['tasks'] = [
      '#tree' => TRUE,
      '#prefix' => '<section class="main"><ul class="todo-list">',
      '#suffix' => '</ul></section>',
    ];

    if (count($this->todoTasks)) {
      foreach ($this->todoTasks as $key => $task) {
        if (in_array($filter, [$task['completed'], 'all'])) {
          $function = $trigger_element && $trigger_element['#name'] == 'edit-' . $key ?
            'edit' :
            'create';
          $function .= 'TaskElement';
          $form['todo_list_wrapper']['tasks'] += $this->$function($task, $key);
        }
      }

      $form['todo_list_wrapper']['footer'] = $this->createFooter($filter);
    }

    $form['#attached']['library'][] = 'todo_list/todoList';
    return $form;
  }

  /**
   * Creates Todo Task element.
   *
   * @param array $task
   *   The task name and status.
   * @param int $element_key
   *   The element index.
   *
   * @return array
   *   The task form element of to do list.
   */
  protected function createTaskElement(array $task, $element_key) {
    $completed_class = $task['completed'] ? 'completed' : '';
    $element[$element_key] = [
      'completed' => [
        '#prefix' => "<li class='{$completed_class}'><div class='task-item'>",
        '#title' => $task['name'],
        '#type' => 'checkbox',
        '#default_value' => $task['completed'],
        '#ajax' => [
          'callback' => '::updateTask',
          'wrapper' => 'todo-list-wrapper',
        ],
      ],
      'edit' => [
        '#suffix' => '</div>',
        '#type' => 'submit',
        '#value' => $this->t('Edit'),
        '#name' => "edit-{$element_key}",
        '#ajax' => [
          'callback' => '::updateTask',
          'wrapper' => 'todo-list-wrapper',
        ],
        '#attributes' => [
          'class' => ['edit'],
        ],
      ],
      'remove' => [
        '#suffix' => '</li>',
        '#type' => 'submit',
        '#value' => $this->t('Clear Task'),
        '#name' => "remove-{$element_key}",
        '#ajax' => [
          'callback' => '::updateTask',
          'wrapper' => 'todo-list-wrapper',
        ],
        '#attributes' => [
          'class' => ['destroy'],
        ],
      ],
    ];
    return $element;
  }

  /**
   * Edits Todo Task element.
   *
   * @param array $task
   *   The task name and status.
   * @param int $element_key
   *   The element index.
   *
   * @return array
   *   The task form element of to do list.
   */
  protected function editTaskElement(array $task, $element_key) {
    $element[$element_key] = [
      'name' => [
        '#prefix' => "<li class='editing'>",
        '#type' => 'textfield',
        '#default_value' => $task['name'],
        '#attributes' => [
          'autocomplete' => 'off',
          'autofocus' => TRUE,
        ],
      ],
      'update' => [
        '#suffix' => '</li>',
        '#type' => 'submit',
        '#value' => $this->t('Update'),
        '#name' => "update-{$element_key}",
        '#ajax' => [
          'callback' => '::updateTask',
          'wrapper' => 'todo-list-wrapper',
        ],
        '#attributes' => [
          'class' => ['update'],
        ],
      ],
    ];
    return $element;
  }

  /**
   * Creates Todo Task element.
   *
   * @param string $filter
   *   The current filter value.
   *
   * @return array
   *   The footer form element of to do list.
   */
  protected function createFooter($filter) {
    $active_tasks = array_filter($this->todoTasks, [$this, 'activeTasks']);
    $completed_tasks = array_filter($this->todoTasks, [$this, 'completedTasks']);
    $active_tasks = $this->formatPlural(count($active_tasks), '@count item left', '@count items left');
    $element = [
      '#prefix' => '<footer class="footer">',
      '#suffix' => '</footer>',
      'todo_count' => [
        '#markup' => '<span class="todo-count">' . $active_tasks . '</span>',
      ],
      'filters' => [
        '#prefix' => '<div class="filters">',
        '#suffix' => '</div>',
        '#type' => 'radios',
        '#options' => [
          'all' => $this->t('All'),
          '0' => $this->t('Active'),
          '1' => $this->t('Completed'),
        ],
        '#default_value' => $filter,
        '#ajax' => [
          'callback' => '::updateTask',
          'wrapper' => 'todo-list-wrapper',
        ],
      ],
    ];

    if (count($completed_tasks)) {
      $element += [
        'clear_completed' => [
          '#type' => 'submit',
          '#value' => $this->t('Clear completed'),
          '#name' => "clear-completed",
          '#ajax' => [
            'callback' => '::updateTask',
            'wrapper' => 'todo-list-wrapper',
          ],
          '#attributes' => [
            'class' => ['clear-completed'],
          ],
        ],
      ];
    }
    return $element;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger_element = $form_state->getTriggeringElement();

    // Add new element in the task list.
    if ($trigger_element['#name'] == 'add-task') {
      $new_todo = $form_state->getValue('new_todo');
      $this->todoTasks = $new_todo ?
        array_merge($this->todoTasks, [['name' => $new_todo, 'completed' => 0]]) :
        $this->todoTasks;
    }
    // Updates an element from the task list.
    elseif (strpos($trigger_element['#name'], 'update-') !== FALSE) {
      $new_value = $form_state->getValue('tasks')[$trigger_element['#parents'][1]]['name'];
      $this->todoTasks[$trigger_element['#parents'][1]]['name'] = $new_value;
    }
    // Removes an element from the task list.
    elseif (strpos($trigger_element['#name'], 'remove-') !== FALSE) {
      unset($this->todoTasks[$trigger_element['#parents'][1]]);
    }
    // Removes completed tasks element from the task list.
    elseif ($trigger_element['#name'] == 'clear-completed') {
      $this->todoTasks = array_filter($this->todoTasks, [$this, 'activeTasks']);
    }

    $this->saveTaskList();

    $user_input = $form_state->getUserInput();
    unset($user_input['new_todo']);
    $form_state->setUserInput($user_input);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax callback for updating task list in form element.
   */
  public function updateTask(array $form, FormStateInterface $form_state) {
    return $form['todo_list_wrapper'];
  }

  /**
   * Creates a list of to do tasks.
   *
   * @return array
   *   The list of to do tasks.
   */
  protected function getTodoTasks() {
    $tasks = $this->requestStack->cookies->get('todo_task_list') ?? [];
    return is_array($tasks) ? $tasks : Json::decode($tasks);
  }

  /**
   * Saves a list of to do tasks.
   */
  protected function saveTaskList() {
    $current_path = $this->currentPath->getPath();
    setcookie('todo_task_list', Json::encode($this->todoTasks), 0, $current_path);
  }

  /**
   * Filter active tasks from the task list.
   */
  protected function activeTasks($task) {
    return $task['completed'] == 0;
  }

  /**
   * Filter active tasks from the task list.
   */
  protected function completedTasks($task) {
    return $task['completed'] == 1;
  }

  /**
   * Filter active tasks from the task list.
   */
  protected function updateTasksList(&$task, $key, $completed) {
    $task['completed'] = $completed;
  }

}
